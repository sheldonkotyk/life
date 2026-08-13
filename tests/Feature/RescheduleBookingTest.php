<?php

use App\Livewire\RescheduleBookingPage;
use App\Mail\BookingHoldPlaced;
use App\Models\Booking;
use App\Models\BookingCalendarSelection;
use App\Models\BookingPage;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\IcsInvite;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-08-11 12:00:00 UTC');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/**
 * A confirmed 09:00-09:30 booking on a Wednesday, on a page open 09:00-11:00.
 */
function bookingToMove(array $attributes = []): Booking
{
    $user = User::factory()->create();
    $connection = connectGoogleCalendar($user);
    $page = BookingPage::factory()->for($user)->create([
        'timezone' => 'UTC',
        'minimum_notice_hours' => 0,
        'buffer_minutes' => 0,
        'duration_minutes' => 30,
        'availability_starts_at' => '09:00',
        'availability_ends_at' => '11:00',
        'available_days' => [3],
    ]);
    BookingCalendarSelection::factory()->for($page)->for($connection, 'connection')->receivesBookings()->create();

    return Booking::factory()->for($page)->create([
        'google_calendar_connection_id' => $connection->id,
        'google_calendar_id' => 'primary',
        'google_event_id' => 'lifebooking-event',
        'starts_at' => CarbonImmutable::parse('2026-08-12 09:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-08-12 09:30:00', 'UTC'),
        ...$attributes,
    ]);
}

it('rejects a reschedule link without a valid signature', function () {
    $booking = bookingToMove();

    $this->get(route('booking.reschedule', [
        'bookingPage' => $booking->bookingPage,
        'booking' => $booking,
    ], false))->assertForbidden();
});

it('moves the booking and patches the Google event', function () {
    Http::preventStrayRequests();
    $booking = bookingToMove();

    Http::fake([
        'www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => ['primary' => ['busy' => []]]]),
        'www.googleapis.com/calendar/v3/calendars/*' => Http::response(['id' => 'lifebooking-event']),
    ]);

    $this->get($booking->rescheduleUrl())->assertOk();

    Livewire::test(RescheduleBookingPage::class, [
        'bookingPage' => $booking->bookingPage,
        'booking' => $booking,
    ])
        ->set('selectedDate', '2026-08-12')
        ->call('selectSlot', '2026-08-12T10:00:00+00:00')
        ->call('reschedule')
        ->assertHasNoErrors()
        ->assertSee('Your meeting has moved');

    $booking->refresh();

    expect($booking->starts_at->toIso8601String())->toBe('2026-08-12T10:00:00+00:00')
        ->and($booking->ends_at->toIso8601String())->toBe('2026-08-12T10:30:00+00:00')
        ->and($booking->rescheduled_at)->not->toBeNull()
        ->and($booking->status)->toBe(Booking::STATUS_CONFIRMED);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
        && str_contains($request->url(), '/calendars/primary/events/lifebooking-event')
        && str_contains($request->url(), 'sendUpdates=all')
        && $request['start']['dateTime'] === '2026-08-12T10:00:00+00:00');
});

it('offers the times its own event occupies rather than blocking them', function () {
    Http::preventStrayRequests();
    $booking = bookingToMove();

    // Google reports the booking's own event as busy, as it always will.
    Http::fake([
        'www.googleapis.com/calendar/v3/freeBusy' => Http::response([
            'calendars' => ['primary' => ['busy' => [[
                'start' => '2026-08-12T09:00:00Z',
                'end' => '2026-08-12T09:30:00Z',
            ]]]],
        ]),
    ]);

    $slots = Livewire::test(RescheduleBookingPage::class, [
        'bookingPage' => $booking->bookingPage,
        'booking' => $booking,
    ])->set('selectedDate', '2026-08-12')->viewData('availableSlots');

    expect(collect($slots)->pluck('start'))->toContain('2026-08-12T09:00:00+00:00');
});

it('still refuses a slot another meeting holds', function () {
    Http::preventStrayRequests();
    $booking = bookingToMove();

    Http::fake([
        'www.googleapis.com/calendar/v3/freeBusy' => Http::response([
            'calendars' => ['primary' => ['busy' => [[
                'start' => '2026-08-12T10:00:00Z',
                'end' => '2026-08-12T10:30:00Z',
            ]]]],
        ]),
    ]);

    Livewire::test(RescheduleBookingPage::class, [
        'bookingPage' => $booking->bookingPage,
        'booking' => $booking,
    ])
        ->set('selectedDate', '2026-08-12')
        ->call('selectSlot', '2026-08-12T10:00:00+00:00')
        ->call('reschedule')
        ->assertHasErrors('selectedStart');

    expect($booking->refresh()->starts_at->toIso8601String())->toBe('2026-08-12T09:00:00+00:00');

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'PATCH');
});

it('keeps the original time when Google refuses the patch', function () {
    Http::preventStrayRequests();
    $booking = bookingToMove();

    Http::fake([
        'www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => ['primary' => ['busy' => []]]]),
        'www.googleapis.com/calendar/v3/calendars/*' => Http::response(['error' => ['message' => 'Backend error']], 500),
    ]);

    Livewire::test(RescheduleBookingPage::class, [
        'bookingPage' => $booking->bookingPage,
        'booking' => $booking,
    ])
        ->set('selectedDate', '2026-08-12')
        ->call('selectSlot', '2026-08-12T10:00:00+00:00')
        ->call('reschedule')
        ->assertHasErrors('selectedStart');

    $booking->refresh();

    expect($booking->starts_at->toIso8601String())->toBe('2026-08-12T09:00:00+00:00')
        ->and($booking->rescheduled_at)->toBeNull();
});

it('moves a held request without accepting it', function () {
    Http::preventStrayRequests();
    Mail::fake();
    $booking = bookingToMove([
        'status' => Booking::STATUS_PENDING,
        'google_ical_uid' => 'lifebooking-event@google.com',
    ]);
    $booking->bookingPage->update(['requires_approval' => true]);

    Http::fake([
        'www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => ['primary' => ['busy' => []]]]),
        'www.googleapis.com/calendar/v3/calendars/*' => Http::response(['id' => 'lifebooking-event']),
    ]);

    Livewire::test(RescheduleBookingPage::class, [
        'bookingPage' => $booking->bookingPage,
        'booking' => $booking,
    ])
        ->set('selectedDate', '2026-08-12')
        ->call('selectSlot', '2026-08-12T10:00:00+00:00')
        ->call('reschedule')
        ->assertHasNoErrors();

    $booking->refresh();

    // The hold moves; it is still a request.
    expect($booking->starts_at->toIso8601String())->toBe('2026-08-12T10:00:00+00:00')
        ->and($booking->status)->toBe(Booking::STATUS_PENDING)
        ->and($booking->rescheduled_at)->not->toBeNull();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH');

    // The guest's calendar is moved by a fresh hold, at a higher sequence.
    Mail::assertSent(BookingHoldPlaced::class, function (BookingHoldPlaced $mail): bool {
        return $mail->moved
            && str_contains(IcsInvite::hold($mail->booking, $mail->organiserEmail, 1), 'SEQUENCE:1');
    });
});

it('will not move a cancelled booking', function () {
    Http::preventStrayRequests();
    $booking = bookingToMove(['status' => Booking::STATUS_CANCELLED, 'cancelled_at' => now()]);

    Livewire::test(RescheduleBookingPage::class, [
        'bookingPage' => $booking->bookingPage,
        'booking' => $booking,
    ])->assertSee('This meeting was cancelled');

    Http::assertNothingSent();
});

it('frees the old time for someone else once moved', function () {
    Http::preventStrayRequests();
    $booking = bookingToMove();

    Http::fake([
        'www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => ['primary' => ['busy' => []]]]),
        'www.googleapis.com/calendar/v3/calendars/*' => Http::response(['id' => 'lifebooking-event']),
    ]);

    Livewire::test(RescheduleBookingPage::class, [
        'bookingPage' => $booking->bookingPage,
        'booking' => $booking,
    ])
        ->set('selectedDate', '2026-08-12')
        ->call('selectSlot', '2026-08-12T10:00:00+00:00')
        ->call('reschedule')
        ->assertHasNoErrors();

    $starts = collect(app(AvailabilityService::class)
        ->slots($booking->bookingPage->fresh(), '2026-08-12'))
        ->pluck('start');

    expect($starts)->toContain('2026-08-12T09:00:00+00:00')
        ->and($starts)->not->toContain('2026-08-12T10:00:00+00:00');
});
