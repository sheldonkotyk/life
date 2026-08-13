<?php

use App\Livewire\BookingSettings;
use App\Livewire\CancelBookingPage;
use App\Livewire\PublicBookingPage;
use App\Models\Booking;
use App\Models\BookingCalendarSelection;
use App\Models\BookingPage;
use App\Models\User;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/**
 * A confirmed booking on a published page, with the Google account and calendar
 * that received the event recorded on it.
 */
function bookingWithGoogleEvent(array $attributes = []): Booking
{
    $user = User::factory()->create();
    $connection = connectGoogleCalendar($user);
    $page = BookingPage::factory()->for($user)->create([
        'timezone' => 'UTC',
        'minimum_notice_hours' => 0,
        'availability_starts_at' => '09:00',
        'availability_ends_at' => '11:00',
        'available_days' => [1, 2, 3, 4, 5],
    ]);
    BookingCalendarSelection::factory()->for($page)->for($connection, 'connection')->receivesBookings()->create();

    return Booking::factory()->for($page)->create([
        'google_calendar_connection_id' => $connection->id,
        'google_calendar_id' => 'primary',
        'google_event_id' => 'lifebooking-event',
        ...$attributes,
    ]);
}

it('rejects a cancel link without a valid signature', function () {
    $booking = bookingWithGoogleEvent();

    $this->get(route('booking.cancel', [
        'bookingPage' => $booking->bookingPage,
        'booking' => $booking,
    ], false))->assertForbidden();

    expect($booking->refresh()->status)->toBe(Booking::STATUS_CONFIRMED);
});

it('lets a guest cancel from the signed link in their invitation', function () {
    Http::preventStrayRequests();
    $booking = bookingWithGoogleEvent();

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/*' => Http::response([], 204),
    ]);

    $this->get($booking->cancelUrl())->assertOk();

    Livewire::test(CancelBookingPage::class, [
        'bookingPage' => $booking->bookingPage,
        'booking' => $booking,
    ])
        ->call('cancel')
        ->assertHasNoErrors()
        ->assertSee('Your meeting is cancelled');

    expect($booking->refresh()->status)->toBe(Booking::STATUS_CANCELLED)
        ->and($booking->cancelled_at)->not->toBeNull();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
        && str_contains($request->url(), '/calendars/primary/events/lifebooking-event')
        && str_contains($request->url(), 'sendUpdates=all'));
});

it('carries the cancel link into the calendar invitation', function () {
    CarbonImmutable::setTestNow('2026-08-11 12:00:00 UTC');
    Http::preventStrayRequests();

    $user = User::factory()->create();
    $connection = connectGoogleCalendar($user);
    $page = BookingPage::factory()->for($user)->create([
        'timezone' => 'UTC',
        'minimum_notice_hours' => 0,
        'buffer_minutes' => 0,
        'availability_starts_at' => '09:00',
        'availability_ends_at' => '11:00',
        'available_days' => [3],
    ]);
    BookingCalendarSelection::factory()->for($page)->for($connection, 'connection')->receivesBookings()->create();

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/events')) {
            return Http::response(['id' => 'lifebooking1', 'htmlLink' => 'https://calendar.google.com/event/1']);
        }

        return Http::response(['calendars' => ['primary' => ['busy' => []]]]);
    });

    Livewire::test(PublicBookingPage::class, ['bookingPage' => $page])
        ->set('selectedDate', '2026-08-12')
        ->set('selectedStart', '2026-08-12T09:00:00+00:00')
        ->set('guestName', 'Alex Guest')
        ->set('guestEmail', 'alex@example.test')
        ->call('book')
        ->assertHasNoErrors();

    $booking = Booking::sole();

    // The guest never signs in, so the signed link in the invitation is the
    // only way back to their booking.
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/events')
        && str_contains($request['description'], $booking->cancelUrl()));

    $this->get($booking->cancelUrl())->assertOk()->assertSee('Cancel this meeting?');
});

it('frees the slot again once a booking is cancelled', function () {
    CarbonImmutable::setTestNow('2026-08-11 12:00:00 UTC');
    Http::preventStrayRequests();

    $booking = bookingWithGoogleEvent([
        'starts_at' => CarbonImmutable::parse('2026-08-12 09:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-08-12 09:30:00', 'UTC'),
    ]);

    Http::fake([
        'www.googleapis.com/calendar/v3/freeBusy' => Http::response([
            'calendars' => ['primary' => ['busy' => []]],
        ]),
        'www.googleapis.com/calendar/v3/calendars/*' => Http::response([], 204),
    ]);

    $availability = app(AvailabilityService::class);
    $starts = fn (): array => collect($availability->slots($booking->bookingPage->fresh(), '2026-08-12'))
        ->pluck('start')
        ->all();

    expect($starts())->not->toContain('2026-08-12T09:00:00+00:00');

    Livewire::test(CancelBookingPage::class, [
        'bookingPage' => $booking->bookingPage,
        'booking' => $booking,
    ])->call('cancel');

    expect($starts())->toContain('2026-08-12T09:00:00+00:00');
});

it('treats a second cancellation as a no-op instead of an error', function () {
    Http::preventStrayRequests();
    $booking = bookingWithGoogleEvent(['status' => Booking::STATUS_CANCELLED, 'cancelled_at' => now()]);

    Livewire::test(CancelBookingPage::class, [
        'bookingPage' => $booking->bookingPage,
        'booking' => $booking,
    ])
        ->assertSee('This meeting was already cancelled')
        ->call('cancel')
        ->assertHasNoErrors();

    Http::assertNothingSent();
});

it('still cancels when the host already deleted the event in Google', function () {
    Http::preventStrayRequests();
    $booking = bookingWithGoogleEvent();

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/*' => Http::response(['error' => ['message' => 'Not Found']], 404),
    ]);

    Livewire::test(CancelBookingPage::class, [
        'bookingPage' => $booking->bookingPage,
        'booking' => $booking,
    ])
        ->call('cancel')
        ->assertHasNoErrors();

    expect($booking->refresh()->status)->toBe(Booking::STATUS_CANCELLED);
});

it('keeps the booking confirmed when Google refuses the delete', function () {
    Http::preventStrayRequests();
    $booking = bookingWithGoogleEvent();

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/*' => Http::response(['error' => ['message' => 'Backend error']], 500),
    ]);

    Livewire::test(CancelBookingPage::class, [
        'bookingPage' => $booking->bookingPage,
        'booking' => $booking,
    ])
        ->call('cancel')
        ->assertHasErrors('cancel');

    expect($booking->refresh()->status)->toBe(Booking::STATUS_CONFIRMED)
        ->and($booking->cancelled_at)->toBeNull();
});

it('lets the host cancel an upcoming booking from their settings', function () {
    Http::preventStrayRequests();
    $booking = bookingWithGoogleEvent();

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/*' => Http::response([], 204),
        'www.googleapis.com/calendar/v3/users/me/calendarList' => Http::response(['items' => []]),
    ]);

    Livewire::actingAs($booking->bookingPage->user)
        ->test(BookingSettings::class)
        ->call('cancelBooking', $booking->id)
        ->assertHasNoErrors();

    expect($booking->refresh()->status)->toBe(Booking::STATUS_CANCELLED);
});

it('cannot cancel a booking that belongs to another host', function () {
    Http::preventStrayRequests();
    $booking = bookingWithGoogleEvent();
    $otherHost = User::factory()->create();
    connectGoogleCalendar($otherHost, 'other@example.test');

    Http::fake([
        'www.googleapis.com/calendar/v3/users/me/calendarList' => Http::response(['items' => []]),
    ]);

    $cancelAttempt = fn () => Livewire::actingAs($otherHost)
        ->test(BookingSettings::class)
        ->call('cancelBooking', $booking->id);

    expect($cancelAttempt)->toThrow(ModelNotFoundException::class)
        ->and($booking->refresh()->status)->toBe(Booking::STATUS_CONFIRMED);
});
