<?php

use App\Livewire\PublicBookingPage;
use App\Models\Booking;
use App\Models\BookingCalendarSelection;
use App\Models\BookingPage;
use App\Models\User;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('returns not found for an unpublished booking page', function () {
    $page = BookingPage::factory()->create(['is_enabled' => false]);

    $this->get(route('booking.show', $page))->assertNotFound();
});

it('groups freebusy requests by Google account and merges their busy periods', function () {
    CarbonImmutable::setTestNow('2026-08-11 12:00:00 UTC');
    Http::preventStrayRequests();

    $user = User::factory()->create();
    $work = connectGoogleCalendar($user, 'work@example.test', 'work-access');
    $personal = connectGoogleCalendar($user, 'personal@example.test', 'personal-access');
    $page = BookingPage::factory()->for($user)->create([
        'timezone' => 'UTC',
        'minimum_notice_hours' => 0,
        'buffer_minutes' => 0,
        'availability_starts_at' => '09:00',
        'availability_ends_at' => '11:00',
        'available_days' => [3],
    ]);
    BookingCalendarSelection::factory()->for($page)->for($work, 'connection')->create([
        'google_calendar_id' => 'primary',
        'google_calendar_name' => 'Work',
    ]);
    BookingCalendarSelection::factory()->for($page)->for($personal, 'connection')->receivesBookings()->create([
        'google_calendar_id' => 'primary',
        'google_calendar_name' => 'Personal',
    ]);

    Http::fake(function (Request $request) {
        $busy = $request->hasHeader('Authorization', 'Bearer work-access')
            ? [['start' => '2026-08-12T09:00:00Z', 'end' => '2026-08-12T09:30:00Z']]
            : [['start' => '2026-08-12T10:00:00Z', 'end' => '2026-08-12T10:30:00Z']];

        return Http::response([
            'calendars' => ['primary' => ['busy' => $busy]],
        ]);
    });

    $slots = app(AvailabilityService::class)->slots($page, '2026-08-12');

    expect(collect($slots)->pluck('label')->all())->toBe([
        '9:30 AM',
        '10:30 AM',
    ]);

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer work-access')
        && $request['items'] === [['id' => 'primary']]);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer personal-access')
        && $request['items'] === [['id' => 'primary']]);
});

it('removes Google busy periods and local bookings from available slots', function () {
    CarbonImmutable::setTestNow('2026-08-11 12:00:00 UTC');
    Http::preventStrayRequests();

    $user = User::factory()->create();
    $connection = connectGoogleCalendar($user);
    $page = BookingPage::factory()->for($user)->create([
        'timezone' => 'UTC',
        'minimum_notice_hours' => 0,
        'buffer_minutes' => 0,
        'availability_starts_at' => '09:00',
        'availability_ends_at' => '12:00',
        'available_days' => [3],
    ]);
    BookingCalendarSelection::factory()->for($page)->for($connection, 'connection')->receivesBookings()->create();
    Booking::factory()->for($page)->create([
        'starts_at' => '2026-08-12 10:00:00',
        'ends_at' => '2026-08-12 10:30:00',
    ]);

    Http::fake([
        'www.googleapis.com/calendar/v3/freeBusy' => Http::response([
            'calendars' => [
                'primary' => [
                    'busy' => [[
                        'start' => '2026-08-12T09:00:00Z',
                        'end' => '2026-08-12T09:30:00Z',
                    ]],
                ],
            ],
        ]),
    ]);

    $slots = app(AvailabilityService::class)->slots($page, '2026-08-12');

    expect(collect($slots)->pluck('label')->all())->toBe([
        '9:30 AM',
        '10:30 AM',
        '11:00 AM',
        '11:30 AM',
    ]);
});

it('creates the event with the selected destination accounts credential', function () {
    CarbonImmutable::setTestNow('2026-08-11 12:00:00 UTC');
    Http::preventStrayRequests();

    $user = User::factory()->create(['name' => 'Taylor Owner']);
    $work = connectGoogleCalendar($user, 'work@example.test', 'work-access');
    $personal = connectGoogleCalendar($user, 'personal@example.test', 'personal-access');
    $page = BookingPage::factory()->for($user)->create([
        'title' => 'Project chat',
        'timezone' => 'UTC',
        'minimum_notice_hours' => 0,
        'buffer_minutes' => 0,
        'availability_starts_at' => '09:00',
        'availability_ends_at' => '11:00',
        'available_days' => [3],
    ]);
    BookingCalendarSelection::factory()->for($page)->for($work, 'connection')->create([
        'google_calendar_id' => 'work-calendar',
        'google_calendar_name' => 'Work',
    ]);
    BookingCalendarSelection::factory()->for($page)->for($personal, 'connection')->receivesBookings()->create([
        'google_calendar_id' => 'personal-calendar',
        'google_calendar_name' => 'Personal',
    ]);

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/events')) {
            return Http::response([
                'id' => 'lifebooking1',
                'htmlLink' => 'https://calendar.google.com/event/1',
            ]);
        }

        $calendarId = $request->hasHeader('Authorization', 'Bearer work-access')
            ? 'work-calendar'
            : 'personal-calendar';

        return Http::response([
            'calendars' => [$calendarId => ['busy' => []]],
        ]);
    });

    Livewire::test(PublicBookingPage::class, ['bookingPage' => $page])
        ->set('selectedDate', '2026-08-12')
        ->set('selectedStart', '2026-08-12T09:30:00+00:00')
        ->set('guestName', 'Alex Guest')
        ->set('guestEmail', 'ALEX@example.test')
        ->set('notes', 'Discuss the new project')
        ->set('guestTimezone', 'America/Winnipeg')
        ->call('book')
        ->assertHasNoErrors()
        ->assertSee("You're booked", escape: false);

    $booking = Booking::sole();

    expect($booking->status)->toBe(Booking::STATUS_CONFIRMED)
        ->and($booking->guest_email)->toBe('alex@example.test')
        ->and($booking->google_event_id)->toBe('lifebooking1');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/calendars/personal-calendar/events')
        && str_contains($request->url(), 'sendUpdates=all')
        && $request->hasHeader('Authorization', 'Bearer personal-access')
        && $request['summary'] === 'Project chat — Alex Guest'
        && $request['attendees'][0]['email'] === 'alex@example.test');
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/events')
        && $request->hasHeader('Authorization', 'Bearer work-access'));
});

it('fails closed when availability from any connected account cannot be read', function () {
    CarbonImmutable::setTestNow('2026-08-11 12:00:00 UTC');
    Http::preventStrayRequests();

    $user = User::factory()->create();
    $work = connectGoogleCalendar($user, 'work@example.test', 'work-access');
    $personal = connectGoogleCalendar($user, 'personal@example.test', 'personal-access');
    $page = BookingPage::factory()->for($user)->create([
        'timezone' => 'UTC',
        'minimum_notice_hours' => 0,
        'available_days' => [3],
    ]);
    BookingCalendarSelection::factory()->for($page)->for($work, 'connection')->receivesBookings()->create();
    BookingCalendarSelection::factory()->for($page)->for($personal, 'connection')->create();

    Http::fake(function (Request $request) {
        if ($request->hasHeader('Authorization', 'Bearer personal-access')) {
            return Http::response(['message' => 'temporarily unavailable'], 503);
        }

        return Http::response(['calendars' => ['primary' => ['busy' => []]]]);
    });

    expect(fn () => app(AvailabilityService::class)->slots($page, '2026-08-12'))
        ->toThrow(RequestException::class);
});

it('rejects a slot that became busy before submission', function () {
    CarbonImmutable::setTestNow('2026-08-11 12:00:00 UTC');
    Http::preventStrayRequests();

    $user = User::factory()->create();
    $connection = connectGoogleCalendar($user);
    $page = BookingPage::factory()->for($user)->create([
        'timezone' => 'UTC',
        'minimum_notice_hours' => 0,
        'availability_starts_at' => '09:00',
        'availability_ends_at' => '11:00',
        'available_days' => [3],
    ]);
    BookingCalendarSelection::factory()->for($page)->for($connection, 'connection')->receivesBookings()->create();

    Http::fake([
        'www.googleapis.com/calendar/v3/freeBusy' => Http::response([
            'calendars' => [
                'primary' => [
                    'busy' => [[
                        'start' => '2026-08-12T09:30:00Z',
                        'end' => '2026-08-12T10:00:00Z',
                    ]],
                ],
            ],
        ]),
    ]);

    Livewire::test(PublicBookingPage::class, ['bookingPage' => $page])
        ->set('selectedDate', '2026-08-12')
        ->set('selectedStart', '2026-08-12T09:30:00+00:00')
        ->set('guestName', 'Alex Guest')
        ->set('guestEmail', 'alex@example.test')
        ->call('book')
        ->assertHasErrors('selectedStart');

    expect(Booking::count())->toBe(0);
});

it('renders the available times as buttons', function () {
    CarbonImmutable::setTestNow('2026-08-11 12:00:00 UTC');
    Http::preventStrayRequests();

    $user = User::factory()->create();
    $connection = connectGoogleCalendar($user);
    $page = BookingPage::factory()->for($user)->create([
        'timezone' => 'UTC',
        'minimum_notice_hours' => 0,
        'buffer_minutes' => 0,
        'availability_starts_at' => '09:00',
        'availability_ends_at' => '10:00',
        'available_days' => [3],
    ]);
    BookingCalendarSelection::factory()->for($page)->for($connection, 'connection')->receivesBookings()->create();

    Http::fake([
        'www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => ['primary' => ['busy' => []]]]),
    ]);

    Livewire::test(PublicBookingPage::class, ['bookingPage' => $page])
        ->set('selectedDate', '2026-08-12')
        ->assertSee('9:00 AM')
        ->assertSee('9:30 AM');
});
