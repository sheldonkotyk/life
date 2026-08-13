<?php

use App\Actions\SyncCalendarChanges;
use App\Mail\BookingGuestDeclined;
use App\Models\Booking;
use App\Models\BookingCalendarSelection;
use App\Models\BookingPage;
use App\Models\CalendarSyncState;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-08-11 12:00:00 UTC');
    Mail::fake();
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/** A confirmed booking sitting on a destination calendar. */
function syncedBooking(array $attributes = []): Booking
{
    $user = User::factory()->create();
    $connection = connectGoogleCalendar($user);
    $page = BookingPage::factory()->for($user)->create([
        'google_calendar_connection_id' => $connection->id,
        'timezone' => 'UTC',
    ]);
    BookingCalendarSelection::factory()->for($page)->for($connection, 'connection')->receivesBookings()->create([
        'google_calendar_id' => 'primary',
    ]);

    return Booking::factory()->for($page)->create([
        'google_calendar_connection_id' => $connection->id,
        'google_calendar_id' => 'primary',
        'google_event_id' => 'lifebooking1',
        'guest_email' => 'alex@example.test',
        'status' => Booking::STATUS_CONFIRMED,
        'starts_at' => CarbonImmutable::parse('2026-08-12 09:00:00', 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-08-12 09:30:00', 'UTC'),
        ...$attributes,
    ]);
}

/** @param array<string, mixed> $event */
function fakeChangedEvents(array $event, ?string $syncToken = 'token-2'): void
{
    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/*/events?*' => Http::response([
            'items' => [$event],
            'nextSyncToken' => $syncToken,
        ]),
        'www.googleapis.com/calendar/v3/calendars/*' => Http::response([], 204),
    ]);
}

it('cancels a booking and frees the time when the guest declines', function () {
    Http::preventStrayRequests();
    $booking = syncedBooking();

    fakeChangedEvents([
        'id' => 'lifebooking1',
        'status' => 'confirmed',
        'start' => ['dateTime' => '2026-08-12T09:00:00Z'],
        'end' => ['dateTime' => '2026-08-12T09:30:00Z'],
        'attendees' => [['email' => 'alex@example.test', 'responseStatus' => 'declined']],
    ]);

    $result = app(SyncCalendarChanges::class)->execute();

    expect($result['changed'])->toBe(1)
        ->and($booking->refresh()->status)->toBe(Booking::STATUS_CANCELLED)
        ->and($booking->cancelled_at)->not->toBeNull();

    // The held entry is removed from the owner's calendar, and they are told.
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE');
    Mail::assertSent(BookingGuestDeclined::class);
});

it('leaves a booking alone while the guest has not answered', function () {
    Http::preventStrayRequests();
    $booking = syncedBooking();

    fakeChangedEvents([
        'id' => 'lifebooking1',
        'status' => 'confirmed',
        'start' => ['dateTime' => '2026-08-12T09:00:00Z'],
        'end' => ['dateTime' => '2026-08-12T09:30:00Z'],
        'attendees' => [['email' => 'alex@example.test', 'responseStatus' => 'needsAction']],
    ]);

    app(SyncCalendarChanges::class)->execute();

    expect($booking->refresh()->status)->toBe(Booking::STATUS_CONFIRMED);
    Mail::assertNothingSent();
});

it('cancels a booking whose event the owner deleted in Google', function () {
    Http::preventStrayRequests();
    $booking = syncedBooking();

    fakeChangedEvents(['id' => 'lifebooking1', 'status' => 'cancelled']);

    app(SyncCalendarChanges::class)->execute();

    expect($booking->refresh()->status)->toBe(Booking::STATUS_CANCELLED);
});

it('follows a meeting the owner moved in Google', function () {
    Http::preventStrayRequests();
    $booking = syncedBooking();

    fakeChangedEvents([
        'id' => 'lifebooking1',
        'status' => 'confirmed',
        'start' => ['dateTime' => '2026-08-12T14:00:00Z'],
        'end' => ['dateTime' => '2026-08-12T14:30:00Z'],
    ]);

    app(SyncCalendarChanges::class)->execute();
    $booking->refresh();

    expect($booking->starts_at->toIso8601String())->toBe('2026-08-12T14:00:00+00:00')
        ->and($booking->ends_at->toIso8601String())->toBe('2026-08-12T14:30:00+00:00')
        ->and($booking->rescheduled_at)->not->toBeNull()
        ->and($booking->status)->toBe(Booking::STATUS_CONFIRMED);
});

it('remembers where it got to and asks only for what changed next time', function () {
    Http::preventStrayRequests();
    syncedBooking();

    fakeChangedEvents(['id' => 'other-event', 'status' => 'confirmed']);

    app(SyncCalendarChanges::class)->execute();

    $state = CalendarSyncState::sole();
    expect($state->sync_token)->toBe('token-2')
        ->and($state->synced_at)->not->toBeNull();

    app(SyncCalendarChanges::class)->execute();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'syncToken=token-2'));
});

it('starts over when Google has forgotten the sync token', function () {
    Http::preventStrayRequests();
    $booking = syncedBooking();
    CalendarSyncState::create([
        'google_calendar_connection_id' => $booking->google_calendar_connection_id,
        'google_calendar_id' => 'primary',
        'sync_token' => 'stale-token',
    ]);

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'syncToken=stale-token')) {
            return Http::response(['error' => ['message' => 'Sync token is no longer valid']], 410);
        }

        return Http::response(['items' => [], 'nextSyncToken' => 'fresh-token']);
    });

    app(SyncCalendarChanges::class)->execute();

    expect(CalendarSyncState::sole()->sync_token)->toBe('fresh-token');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'timeMin='));
});

it('changes nothing when Google cannot be read', function () {
    Http::preventStrayRequests();
    $booking = syncedBooking();

    Http::fake(['www.googleapis.com/*' => Http::response(['error' => ['message' => 'Backend error']], 500)]);

    $result = app(SyncCalendarChanges::class)->execute();

    expect($result['failed'])->toBe(1)
        ->and($booking->refresh()->status)->toBe(Booking::STATUS_CONFIRMED);

    Mail::assertNothingSent();
});
