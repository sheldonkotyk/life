<?php

use App\Livewire\Today;
use App\Models\BookingCalendarSelection;
use App\Models\BookingPage;
use App\Models\Household;
use App\Models\User;
use App\Services\DayAgenda;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-08-13 15:00:00 UTC');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/** A user whose two Google accounts each check one calendar for conflicts. */
function userWithCalendars(): User
{
    $user = loginUser();
    $user->update(['timezone' => 'America/Winnipeg']);

    $work = connectGoogleCalendar($user, 'work@example.test', 'work-token', googleUserId: 'google-work');
    $personal = connectGoogleCalendar($user, 'personal@example.test', 'personal-token', googleUserId: 'google-personal');

    $workPage = BookingPage::factory()->for($user)->create(['google_calendar_connection_id' => $work->id]);
    $personalPage = BookingPage::factory()->for($user)->create(['google_calendar_connection_id' => $personal->id]);

    BookingCalendarSelection::factory()->for($workPage)->for($work, 'connection')->receivesBookings()->create([
        'google_calendar_id' => 'primary',
        'google_calendar_name' => 'Work',
    ]);
    BookingCalendarSelection::factory()->for($personalPage)->for($personal, 'connection')->receivesBookings()->create([
        'google_calendar_id' => 'primary',
        'google_calendar_name' => 'Personal',
    ]);

    return $user->fresh();
}

it('shows today merged across every connected account', function () {
    Http::preventStrayRequests();
    $user = userWithCalendars();

    Http::fake(function (Request $request) {
        $isWork = $request->hasHeader('Authorization', 'Bearer work-token');

        return Http::response(['items' => $isWork
            ? [[
                'id' => 'w1',
                'summary' => 'Standup',
                'start' => ['dateTime' => '2026-08-13T09:00:00-05:00'],
                'end' => ['dateTime' => '2026-08-13T09:15:00-05:00'],
            ]]
            : [[
                'id' => 'p1',
                'summary' => 'Dentist',
                'start' => ['dateTime' => '2026-08-13T08:00:00-05:00'],
                'end' => ['dateTime' => '2026-08-13T08:45:00-05:00'],
            ], [
                'id' => 'p2',
                'summary' => 'Kristin away',
                'start' => ['date' => '2026-08-13'],
                'end' => ['date' => '2026-08-14'],
            ]],
        ]);
    });

    $agenda = app(DayAgenda::class)->forUser($user, CarbonImmutable::today('America/Winnipeg'), 'America/Winnipeg');

    // All-day first, then chronological, regardless of which account they came from.
    expect(collect($agenda['events'])->pluck('title')->all())->toBe(['Kristin away', 'Dentist', 'Standup'])
        ->and($agenda['calendars'])->toBe(2)
        ->and($agenda['failed'])->toBeFalse()
        ->and(collect($agenda['events'])->pluck('account')->unique()->sort()->values()->all())
        ->toBe(['personal@example.test', 'work@example.test']);
});

it('puts the day on the Today screen', function () {
    Http::preventStrayRequests();
    $user = userWithCalendars();
    Household::find($user->household_id) ?? null;

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/*' => Http::response(['items' => [[
            'id' => 'w1',
            'summary' => 'Roadmap review',
            'start' => ['dateTime' => '2026-08-13T09:00:00-05:00'],
            'end' => ['dateTime' => '2026-08-13T10:00:00-05:00'],
        ]]]),
    ]);

    Livewire::test(Today::class)
        ->assertSee('Your calendar')
        ->assertSee('Roadmap review')
        ->assertSee('9:00 AM – 10:00 AM', escape: false);
});

it('says so quietly when one account cannot be read', function () {
    Http::preventStrayRequests();
    $user = userWithCalendars();

    Http::fake(function (Request $request) {
        if ($request->hasHeader('Authorization', 'Bearer work-token')) {
            return Http::response(['error' => ['message' => 'Backend error']], 500);
        }

        return Http::response(['items' => [[
            'id' => 'p1',
            'summary' => 'Dentist',
            'start' => ['dateTime' => '2026-08-13T08:00:00-05:00'],
            'end' => ['dateTime' => '2026-08-13T08:45:00-05:00'],
        ]]]);
    });

    $agenda = app(DayAgenda::class)->forUser($user, CarbonImmutable::today('America/Winnipeg'), 'America/Winnipeg');

    // The readable account still shows.
    expect($agenda['failed'])->toBeTrue()
        ->and(collect($agenda['events'])->pluck('title')->all())->toBe(['Dentist']);
});

it('asks Google once and serves the rest from cache', function () {
    Http::preventStrayRequests();
    $user = userWithCalendars();

    Http::fake(['www.googleapis.com/calendar/v3/calendars/*' => Http::response(['items' => []])]);

    $day = CarbonImmutable::today('America/Winnipeg');
    app(DayAgenda::class)->forUser($user, $day, 'America/Winnipeg');
    app(DayAgenda::class)->forUser($user, $day, 'America/Winnipeg');

    Http::assertSentCount(2);
});

it('shows nothing at all when no calendars are connected', function () {
    Http::preventStrayRequests();
    loginUser();

    Livewire::test(Today::class)->assertDontSee('Your calendar');

    Http::assertNothingSent();
});

it('carries guests and the description through to the screen', function () {
    Http::preventStrayRequests();
    userWithCalendars();

    Http::fake(['www.googleapis.com/calendar/v3/calendars/*' => Http::response(['items' => [[
        'id' => 'w1',
        'summary' => 'Roadmap review',
        'location' => 'Boardroom',
        'description' => 'Bring the draft.<br>Second line.',
        'start' => ['dateTime' => '2026-08-13T09:00:00-05:00'],
        'end' => ['dateTime' => '2026-08-13T10:00:00-05:00'],
        'organizer' => ['email' => 'work@example.test'],
        'attendees' => [
            ['email' => 'work@example.test', 'self' => true, 'organizer' => true, 'responseStatus' => 'accepted'],
            ['email' => 'alex@example.test', 'displayName' => 'Alex Guest', 'responseStatus' => 'declined'],
            ['email' => 'sam@example.test', 'responseStatus' => 'needsAction'],
            ['email' => 'room-3@example.test', 'resource' => true, 'responseStatus' => 'accepted'],
        ],
    ]]])]);

    Livewire::test(Today::class)
        // The row summarises, counting neither the viewer nor the meeting room.
        ->assertSee('2 guests')
        ->assertSee('Alex Guest')
        ->assertSee('Declined')
        ->assertSee('No reply')
        ->assertSee('Boardroom')
        // Google's html is shown as text rather than rendered.
        ->assertSee('Bring the draft.')
        ->assertDontSee('<br>', escape: false);
});
