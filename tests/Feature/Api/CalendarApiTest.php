<?php

use App\Models\BookingCalendarSelection;
use App\Models\BookingPage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-08-19 15:00:00 UTC');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function apiUserWithCalendar(): User
{
    $user = loginApiUser();
    $user->update(['timezone' => 'America/Winnipeg']);

    $connection = connectGoogleCalendar($user, 'work@example.test', 'work-token', googleUserId: 'google-work');
    $page = BookingPage::factory()->for($user)->create(['google_calendar_connection_id' => $connection->id]);
    BookingCalendarSelection::factory()->for($page)->for($connection, 'connection')->receivesBookings()->create([
        'google_calendar_id' => 'primary',
        'google_calendar_name' => 'Work',
    ]);

    return $user->fresh();
}

it('answers with an empty week when no calendar is connected', function () {
    loginApiUser();

    $response = $this->getJson('/api/calendar?anchor=2026-08-19')->assertOk();

    expect($response->json('view'))->toBe('week')
        ->and($response->json('from'))->toBe('2026-08-16')
        ->and($response->json('to'))->toBe('2026-08-22')
        ->and($response->json('days'))->toHaveCount(7)
        ->and($response->json('events'))->toBe([])
        ->and($response->json('calendars'))->toBe(0);
});

it('files an event under the day it happens', function () {
    Http::preventStrayRequests();
    apiUserWithCalendar();

    Http::fake(fn (Request $request) => Http::response(['items' => [[
        'id' => 'e1',
        'summary' => 'Standup',
        'start' => ['dateTime' => '2026-08-19T09:00:00-05:00'],
        'end' => ['dateTime' => '2026-08-19T09:15:00-05:00'],
    ]]]));

    $response = $this->getJson('/api/calendar?view=day&anchor=2026-08-19')->assertOk();

    expect($response->json('view'))->toBe('day')
        ->and($response->json('events'))->toHaveCount(1)
        ->and($response->json('events.0.title'))->toBe('Standup')
        ->and($response->json('events_by_day.2026-08-19'))->toHaveCount(1);
});

it('shows a multi-day stay on every day it covers', function () {
    Http::preventStrayRequests();
    apiUserWithCalendar();

    Http::fake(fn (Request $request) => Http::response(['items' => [[
        'id' => 'e2',
        'summary' => 'Cabin',
        'start' => ['date' => '2026-08-18'],
        'end' => ['date' => '2026-08-21'],
    ]]]));

    $byDay = $this->getJson('/api/calendar?view=week&anchor=2026-08-19')->assertOk()->json('events_by_day');

    expect($byDay['2026-08-18'])->toHaveCount(1)
        ->and($byDay['2026-08-19'])->toHaveCount(1)
        ->and($byDay['2026-08-20'])->toHaveCount(1)
        ->and($byDay['2026-08-21'])->toBe([]);
});

it('spans whole weeks for the month grid', function () {
    loginApiUser();

    $response = $this->getJson('/api/calendar?view=month&anchor=2026-08-19')->assertOk();

    expect($response->json('title'))->toBe('August 2026')
        ->and($response->json('from'))->toBe('2026-07-26')
        ->and($response->json('to'))->toBe('2026-09-05');
});

it('falls back to the week view for a nonsense view', function () {
    loginApiUser();

    $this->getJson('/api/calendar?view=decade')->assertOk()->assertJsonPath('view', 'week');
});
