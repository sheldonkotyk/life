<?php

use App\Livewire\BookingSettings;
use App\Livewire\PublicBookingPage;
use App\Models\Booking;
use App\Models\BookingCalendarSelection;
use App\Models\BookingPage;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-08-11 12:00:00 UTC');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/** A published page that holds requests until its owner answers. */
function approvalPage(): BookingPage
{
    $user = loginUser();
    $connection = connectGoogleCalendar($user);
    $page = BookingPage::factory()->for($user)->create([
        'google_calendar_connection_id' => $connection->id,
        'requires_approval' => true,
        'timezone' => 'UTC',
        'minimum_notice_hours' => 0,
        'buffer_minutes' => 0,
        'availability_starts_at' => '09:00',
        'availability_ends_at' => '11:00',
        'available_days' => [3],
    ]);
    BookingCalendarSelection::factory()->for($page)->for($connection, 'connection')->receivesBookings()->create();

    return $page;
}

it('holds the request without touching the calendar', function () {
    Http::preventStrayRequests();
    $page = approvalPage();

    Http::fake([
        'www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => ['primary' => ['busy' => []]]]),
    ]);

    Livewire::test(PublicBookingPage::class, ['bookingPage' => $page])
        ->set('selectedDate', '2026-08-12')
        ->set('selectedStart', '2026-08-12T09:00:00+00:00')
        ->set('guestName', 'Alex Guest')
        ->set('guestEmail', 'alex@example.test')
        ->call('book')
        ->assertHasNoErrors()
        ->assertSee('Request sent');

    $booking = Booking::sole();

    expect($booking->status)->toBe(Booking::STATUS_PENDING)
        ->and($booking->google_event_id)->toBeNull();

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/events'));
});

it('keeps the held time off the page while it waits', function () {
    Http::preventStrayRequests();
    $page = approvalPage();
    Booking::factory()->for($page)->create([
        'status' => Booking::STATUS_PENDING,
        'google_event_id' => null,
        'starts_at' => CarbonImmutable::parse('2026-08-12 09:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-08-12 09:30:00', 'UTC'),
    ]);

    Http::fake([
        'www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => ['primary' => ['busy' => []]]]),
    ]);

    $starts = collect(app(AvailabilityService::class)->slots($page, '2026-08-12'))->pluck('start');

    expect($starts)->not->toContain('2026-08-12T09:00:00+00:00');
});

it('creates the event only once the owner accepts', function () {
    Http::preventStrayRequests();
    $page = approvalPage();
    $booking = Booking::factory()->for($page)->create([
        'status' => Booking::STATUS_PENDING,
        'google_event_id' => null,
        'google_calendar_id' => null,
        'starts_at' => CarbonImmutable::parse('2026-08-12 09:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-08-12 09:30:00', 'UTC'),
    ]);

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/events')) {
            return Http::response(['id' => 'lifebooking1', 'htmlLink' => 'https://calendar.google.com/event/1']);
        }

        return Http::response(['calendars' => ['primary' => ['busy' => []]], 'items' => []]);
    });

    Livewire::test(BookingSettings::class)
        ->assertSee('Requests waiting on you')
        ->call('acceptBooking', $booking->id)
        ->assertHasNoErrors();

    $booking->refresh();

    expect($booking->status)->toBe(Booking::STATUS_CONFIRMED)
        ->and($booking->google_event_id)->toBe('lifebooking1')
        ->and($booking->responded_at)->not->toBeNull();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/events')
        && $request->method() === 'POST');
});

it('frees the time when the owner declines', function () {
    Http::preventStrayRequests();
    $page = approvalPage();
    $booking = Booking::factory()->for($page)->create([
        'status' => Booking::STATUS_PENDING,
        'google_event_id' => null,
        'starts_at' => CarbonImmutable::parse('2026-08-12 09:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-08-12 09:30:00', 'UTC'),
    ]);

    Http::fake([
        'www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response(['items' => []]),
        'www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => ['primary' => ['busy' => []]]]),
    ]);

    Livewire::test(BookingSettings::class)
        ->call('declineBooking', $booking->id)
        ->assertHasNoErrors();

    expect($booking->refresh()->status)->toBe(Booking::STATUS_REJECTED);

    $starts = collect(app(AvailabilityService::class)->slots($page->fresh(), '2026-08-12'))->pluck('start');

    expect($starts)->toContain('2026-08-12T09:00:00+00:00');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/events'));
});

it('refuses to accept a request whose time has since been taken', function () {
    Http::preventStrayRequests();
    $page = approvalPage();
    $booking = Booking::factory()->for($page)->create([
        'status' => Booking::STATUS_PENDING,
        'google_event_id' => null,
        'starts_at' => CarbonImmutable::parse('2026-08-12 09:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-08-12 09:30:00', 'UTC'),
    ]);

    // The owner booked something else into that time in the meantime.
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'freeBusy')) {
            return Http::response(['calendars' => ['primary' => ['busy' => [[
                'start' => '2026-08-12T09:00:00Z',
                'end' => '2026-08-12T09:30:00Z',
            ]]]]]);
        }

        return Http::response(['items' => []]);
    });

    Livewire::test(BookingSettings::class)
        ->call('acceptBooking', $booking->id)
        ->assertHasErrors('bookings');

    expect($booking->refresh()->status)->toBe(Booking::STATUS_PENDING);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/events'));
});

it('books straight into the calendar when approval is off', function () {
    Http::preventStrayRequests();
    $page = approvalPage();
    $page->update(['requires_approval' => false]);

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/events')) {
            return Http::response(['id' => 'lifebooking1', 'htmlLink' => 'https://calendar.google.com/event/1']);
        }

        return Http::response(['calendars' => ['primary' => ['busy' => []]]]);
    });

    Livewire::test(PublicBookingPage::class, ['bookingPage' => $page->fresh()])
        ->set('selectedDate', '2026-08-12')
        ->set('selectedStart', '2026-08-12T09:00:00+00:00')
        ->set('guestName', 'Alex Guest')
        ->set('guestEmail', 'alex@example.test')
        ->call('book')
        ->assertHasNoErrors()
        ->assertSee("You're booked", escape: false);

    expect(Booking::sole()->status)->toBe(Booking::STATUS_CONFIRMED);
});
