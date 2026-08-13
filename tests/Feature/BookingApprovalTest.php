<?php

use App\Livewire\BookingSettings;
use App\Livewire\Profile;
use App\Livewire\PublicBookingPage;
use App\Mail\BookingHoldPlaced;
use App\Mail\BookingHoldReleased;
use App\Mail\BookingReceived;
use App\Models\Booking;
use App\Models\BookingCalendarSelection;
use App\Models\BookingPage;
use App\Services\AvailabilityService;
use App\Services\IcsInvite;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-08-11 12:00:00 UTC');
    Mail::fake();
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

it('pencils the request into the owner calendar without inviting the guest', function () {
    Http::preventStrayRequests();
    $page = approvalPage();

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/events')) {
            return Http::response([
                'id' => 'lifebooking1',
                'iCalUID' => 'lifebooking1@google.com',
                'htmlLink' => 'https://calendar.google.com/event/1',
            ]);
        }

        return Http::response(['calendars' => ['primary' => ['busy' => []]]]);
    });

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
        ->and($booking->google_event_id)->toBe('lifebooking1')
        ->and($booking->google_ical_uid)->toBe('lifebooking1@google.com');

    // Tentative, and with no attendee the answer links stay private to the owner.
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/events')
        && $request['status'] === 'tentative'
        && ! isset($request['attendees'])
        && str_contains($request->url(), 'sendUpdates=none')
        && str_contains($request['description'], $booking->acceptUrl())
        && str_contains($request['description'], $booking->declineUrl()));

    // The guest's own calendar holds the time through an emailed invitation.
    Mail::assertSent(BookingHoldPlaced::class, fn (BookingHoldPlaced $mail): bool => $mail->hasTo('alex@example.test'));
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

it('confirms the held event and invites the guest when the owner accepts', function () {
    Http::preventStrayRequests();
    $page = approvalPage();
    $booking = Booking::factory()->for($page)->create([
        'status' => Booking::STATUS_PENDING,
        'google_calendar_connection_id' => $page->google_calendar_connection_id,
        'google_calendar_id' => 'primary',
        'google_event_id' => 'lifebooking1',
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
        ->and($booking->responded_at)->not->toBeNull();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
        && str_contains($request->url(), '/events/lifebooking1')
        && $request['status'] === 'confirmed'
        && $request['attendees'][0]['email'] === $booking->guest_email
        && str_contains($request->url(), 'sendUpdates=all'));
});

it('frees the time when the owner declines', function () {
    Http::preventStrayRequests();
    $page = approvalPage();
    $booking = Booking::factory()->for($page)->create([
        'status' => Booking::STATUS_PENDING,
        'google_calendar_connection_id' => $page->google_calendar_connection_id,
        'google_calendar_id' => 'primary',
        'google_event_id' => 'lifebooking1',
        'starts_at' => CarbonImmutable::parse('2026-08-12 09:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-08-12 09:30:00', 'UTC'),
    ]);

    Http::fake([
        'www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response(['items' => []]),
        'www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => ['primary' => ['busy' => []]]]),
        'www.googleapis.com/calendar/v3/calendars/*' => Http::response([], 204),
    ]);

    Livewire::test(BookingSettings::class)
        ->call('declineBooking', $booking->id)
        ->assertHasNoErrors();

    expect($booking->refresh()->status)->toBe(Booking::STATUS_REJECTED);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE');
    Mail::assertSent(BookingHoldReleased::class);

    $starts = collect(app(AvailabilityService::class)->slots($page->fresh(), '2026-08-12'))->pluck('start');

    expect($starts)->toContain('2026-08-12T09:00:00+00:00');
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

it('answers from the link in the calendar entry without signing in', function () {
    Http::preventStrayRequests();
    $page = approvalPage();
    $booking = Booking::factory()->for($page)->create([
        'status' => Booking::STATUS_PENDING,
        'google_calendar_connection_id' => $page->google_calendar_connection_id,
        'google_calendar_id' => 'primary',
        'google_event_id' => 'lifebooking1',
        'starts_at' => CarbonImmutable::parse('2026-08-12 09:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-08-12 09:30:00', 'UTC'),
    ]);

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'freeBusy')) {
            return Http::response(['calendars' => ['primary' => ['busy' => []]]]);
        }

        return Http::response(['id' => 'lifebooking1']);
    });

    auth()->logout();

    $this->get($booking->acceptUrl())->assertOk()->assertSee('Accepted');

    expect($booking->refresh()->status)->toBe(Booking::STATUS_CONFIRMED);
});

it('refuses an answer link that was not signed', function () {
    $page = approvalPage();
    $booking = Booking::factory()->for($page)->create([
        'status' => Booking::STATUS_PENDING,
        'google_event_id' => 'lifebooking1',
    ]);

    auth()->logout();

    $this->get(route('booking.decline', [
        'bookingPage' => $page,
        'booking' => $booking,
    ], false))->assertForbidden();

    expect($booking->refresh()->status)->toBe(Booking::STATUS_PENDING);
});

it('emails the owner about a request with the same answer links', function () {
    Http::preventStrayRequests();
    $page = approvalPage();

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/events')) {
            return Http::response(['id' => 'lifebooking1', 'iCalUID' => 'lifebooking1@google.com']);
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

    Mail::assertSent(BookingReceived::class, function (BookingReceived $mail) use ($page, $booking): bool {
        return $mail->hasTo($page->user->email)
            && str_contains($mail->render(), $booking->acceptUrl())
            && str_contains($mail->render(), $booking->declineUrl());
    });
});

it('emails the owner about a booking that needed no approval', function () {
    Http::preventStrayRequests();
    $page = approvalPage();
    $page->update(['requires_approval' => false]);

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/events')) {
            return Http::response(['id' => 'lifebooking1', 'iCalUID' => 'lifebooking1@google.com']);
        }

        return Http::response(['calendars' => ['primary' => ['busy' => []]]]);
    });

    Livewire::test(PublicBookingPage::class, ['bookingPage' => $page->fresh()])
        ->set('selectedDate', '2026-08-12')
        ->set('selectedStart', '2026-08-12T09:00:00+00:00')
        ->set('guestName', 'Alex Guest')
        ->set('guestEmail', 'alex@example.test')
        ->call('book')
        ->assertHasNoErrors();

    Mail::assertSent(BookingReceived::class);
    Mail::assertNotSent(BookingHoldPlaced::class);
});

it('keeps booking mail flowing when general notifications are off', function () {
    Http::preventStrayRequests();
    $page = approvalPage();
    // Booking mail is transactional, so the general switch does not silence it.
    $page->user->update(['notification_preferences' => ['email' => false]]);

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/events')) {
            return Http::response(['id' => 'lifebooking1', 'iCalUID' => 'lifebooking1@google.com']);
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

    Mail::assertSent(BookingReceived::class);
});

it('stops booking mail when its own switch is off', function () {
    Http::preventStrayRequests();
    $page = approvalPage();
    $page->user->update(['booking_emails_enabled' => false]);

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/events')) {
            return Http::response(['id' => 'lifebooking1', 'iCalUID' => 'lifebooking1@google.com']);
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

    Mail::assertNotSent(BookingReceived::class);
    // The guest still gets their hold; only the owner's note is suppressed.
    Mail::assertSent(BookingHoldPlaced::class);
});
it('gives the guest a way to change the time from their held entry', function () {
    $page = approvalPage();
    $booking = Booking::factory()->for($page)->create([
        'status' => Booking::STATUS_PENDING,
        'google_ical_uid' => 'lifebooking1@google.com',
    ]);

    $ics = IcsInvite::hold($booking, 'owner@example.test');

    expect($ics)->toContain('UID:lifebooking1@google.com')
        ->and($ics)->toContain('STATUS:TENTATIVE')
        ->and($ics)->toContain('METHOD:REQUEST')
        ->and($ics)->toContain(str_replace(['\\', ',', ';'], ['\\\\', '\,', '\;'], $booking->rescheduleUrl()));
});

it('lets the owner turn booking mail off from their profile', function () {
    $user = loginUser();

    expect($user->wantsBookingEmails())->toBeTrue();

    Livewire::test(Profile::class)
        ->assertSet('bookingEmailsEnabled', true)
        ->set('bookingEmailsEnabled', false);

    expect($user->fresh()->wantsBookingEmails())->toBeFalse();
});
