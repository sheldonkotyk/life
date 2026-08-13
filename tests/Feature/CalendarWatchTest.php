<?php

use App\Actions\WatchCalendars;
use App\Models\Booking;
use App\Models\BookingCalendarSelection;
use App\Models\BookingPage;
use App\Models\CalendarSyncState;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-08-11 12:00:00 UTC');
    config()->set('services.google.calendar_webhook_url', 'https://life.test/webhooks/google-calendar');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/** A page whose destination calendar is worth watching. */
function watchablePage(): BookingPage
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

    return $page;
}

it('opens a channel on each calendar that receives bookings', function () {
    Http::preventStrayRequests();
    watchablePage();

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/*/events/watch' => Http::response([
            'resourceId' => 'resource-1',
            'expiration' => (string) CarbonImmutable::parse('2026-08-18 12:00:00 UTC')->getTimestampMs(),
        ]),
    ]);

    $result = app(WatchCalendars::class)->execute();

    $state = CalendarSyncState::sole();

    expect($result['watched'])->toBe(1)
        ->and($state->channel_id)->not->toBeNull()
        ->and($state->channel_resource_id)->toBe('resource-1')
        ->and($state->channel_token)->not->toBeNull()
        ->and($state->channel_expires_at->toDateTimeString())->toBe('2026-08-18 12:00:00');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/events/watch')
        && $request['type'] === 'web_hook'
        && $request['address'] === 'https://life.test/webhooks/google-calendar'
        && $request['id'] === $state->channel_id
        && $request['token'] === $state->channel_token);
});

it('leaves a channel alone until it is close to expiring', function () {
    Http::preventStrayRequests();
    $page = watchablePage();
    CalendarSyncState::create([
        'google_calendar_connection_id' => $page->google_calendar_connection_id,
        'google_calendar_id' => 'primary',
        'channel_id' => (string) Str::uuid(),
        'channel_resource_id' => 'resource-1',
        'channel_token' => 'secret',
        'channel_expires_at' => now()->addDays(3),
    ]);

    $result = app(WatchCalendars::class)->execute();

    expect($result['watched'])->toBe(0);
    Http::assertNothingSent();
});

it('stops the old channel before opening its replacement', function () {
    Http::preventStrayRequests();
    $page = watchablePage();
    CalendarSyncState::create([
        'google_calendar_connection_id' => $page->google_calendar_connection_id,
        'google_calendar_id' => 'primary',
        'channel_id' => '11111111-1111-1111-1111-111111111111',
        'channel_resource_id' => 'resource-old',
        'channel_token' => 'old-secret',
        'channel_expires_at' => now()->addHour(),
    ]);

    Http::fake([
        'www.googleapis.com/calendar/v3/channels/stop' => Http::response([], 204),
        'www.googleapis.com/calendar/v3/calendars/*/events/watch' => Http::response([
            'resourceId' => 'resource-new',
            'expiration' => (string) now()->addWeek()->getTimestampMs(),
        ]),
    ]);

    $result = app(WatchCalendars::class)->execute();

    expect($result['renewed'])->toBe(1)
        ->and(CalendarSyncState::sole()->channel_resource_id)->toBe('resource-new');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/channels/stop')
        && $request['id'] === '11111111-1111-1111-1111-111111111111'
        && $request['resourceId'] === 'resource-old');
});

it('will not subscribe to an address Google would refuse', function () {
    Http::preventStrayRequests();
    watchablePage();
    config()->set('services.google.calendar_webhook_url', 'http://localhost/webhooks/google-calendar');

    expect(app(WatchCalendars::class)->execute())->toBe(['watched' => 0, 'renewed' => 0, 'failed' => 0]);

    Http::assertNothingSent();
});

it('reconciles the calendar a notification names', function () {
    Http::preventStrayRequests();
    $page = watchablePage();
    $booking = Booking::factory()->for($page)->create([
        'google_calendar_connection_id' => $page->google_calendar_connection_id,
        'google_calendar_id' => 'primary',
        'google_event_id' => 'lifebooking1',
        'status' => Booking::STATUS_CONFIRMED,
    ]);
    $state = CalendarSyncState::create([
        'google_calendar_connection_id' => $page->google_calendar_connection_id,
        'google_calendar_id' => 'primary',
        'channel_id' => '22222222-2222-2222-2222-222222222222',
        'channel_resource_id' => 'resource-1',
        'channel_token' => 'shared-secret',
        'channel_expires_at' => now()->addWeek(),
    ]);

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/*/events?*' => Http::response([
            'items' => [['id' => 'lifebooking1', 'status' => 'cancelled']],
            'nextSyncToken' => 'token-2',
        ]),
    ]);

    $this->withHeaders([
        'X-Goog-Channel-ID' => $state->channel_id,
        'X-Goog-Channel-Token' => 'shared-secret',
        'X-Goog-Resource-State' => 'exists',
    ])->post(route('google-calendar.webhook'))->assertNoContent();

    expect($booking->refresh()->status)->toBe(Booking::STATUS_CANCELLED);
});

it('ignores a notification whose token does not match', function () {
    Http::preventStrayRequests();
    $page = watchablePage();
    $state = CalendarSyncState::create([
        'google_calendar_connection_id' => $page->google_calendar_connection_id,
        'google_calendar_id' => 'primary',
        'channel_id' => '33333333-3333-3333-3333-333333333333',
        'channel_resource_id' => 'resource-1',
        'channel_token' => 'shared-secret',
        'channel_expires_at' => now()->addWeek(),
    ]);

    $this->withHeaders([
        'X-Goog-Channel-ID' => $state->channel_id,
        'X-Goog-Channel-Token' => 'guessed',
        'X-Goog-Resource-State' => 'exists',
    ])->post(route('google-calendar.webhook'))->assertNoContent();

    Http::assertNothingSent();
});

it('ignores the handshake message and an unknown channel', function () {
    Http::preventStrayRequests();
    $page = watchablePage();
    $state = CalendarSyncState::create([
        'google_calendar_connection_id' => $page->google_calendar_connection_id,
        'google_calendar_id' => 'primary',
        'channel_id' => '44444444-4444-4444-4444-444444444444',
        'channel_resource_id' => 'resource-1',
        'channel_token' => 'shared-secret',
        'channel_expires_at' => now()->addWeek(),
    ]);

    $this->withHeaders([
        'X-Goog-Channel-ID' => $state->channel_id,
        'X-Goog-Channel-Token' => 'shared-secret',
        'X-Goog-Resource-State' => 'sync',
    ])->post(route('google-calendar.webhook'))->assertNoContent();

    $this->withHeaders([
        'X-Goog-Channel-ID' => '55555555-5555-5555-5555-555555555555',
        'X-Goog-Channel-Token' => 'shared-secret',
        'X-Goog-Resource-State' => 'exists',
    ])->post(route('google-calendar.webhook'))->assertNoContent();

    Http::assertNothingSent();
});
