<?php

use App\Livewire\Calendar;
use App\Models\BookingCalendarSelection;
use App\Models\BookingPage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    // A Thursday, so the surrounding Sunday-to-Saturday week is unambiguous.
    CarbonImmutable::setTestNow('2026-08-13 15:00:00 UTC');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/** A user with a single connected calendar that blocks availability. */
function userWithOneCalendar(): User
{
    $user = loginUser();
    $user->update(['timezone' => 'America/Winnipeg']);

    $connection = connectGoogleCalendar($user, 'work@example.test', 'work-token');
    $page = BookingPage::factory()->for($user)->create(['google_calendar_connection_id' => $connection->id]);

    BookingCalendarSelection::factory()->for($page)->for($connection, 'connection')->receivesBookings()->create([
        'google_calendar_id' => 'primary',
        'google_calendar_name' => 'Work',
    ]);

    return $user->fresh();
}

/** @param  list<array<string, mixed>>  $items */
function fakeCalendar(array $items = []): void
{
    Http::preventStrayRequests();
    Http::fake(['www.googleapis.com/calendar/v3/calendars/*' => Http::response(['items' => $items])]);
}

it('opens on the week around today', function () {
    userWithOneCalendar();
    fakeCalendar([[
        'id' => 'w1',
        'summary' => 'Standup',
        'start' => ['dateTime' => '2026-08-11T09:00:00-05:00'],
        'end' => ['dateTime' => '2026-08-11T09:15:00-05:00'],
    ]]);

    Livewire::test(Calendar::class)
        ->assertSet('view', 'week')
        ->assertSee('Aug 9 – 15, 2026')
        ->assertSee('Standup');

    // Sunday the 9th through Saturday the 15th, in the user's zone.
    Http::assertSent(fn (Request $request) => str_contains(urldecode($request->url()), 'timeMin=2026-08-09T05:00:00+00:00')
        && str_contains(urldecode($request->url()), 'timeMax=2026-08-16T04:59:59+00:00'));
});

it('steps a week at a time and comes back to today', function () {
    userWithOneCalendar();
    fakeCalendar();

    Livewire::test(Calendar::class)
        ->call('shift', 1)
        ->assertSee('Aug 16 – 22, 2026')
        ->call('shift', -1)
        ->call('shift', -1)
        ->assertSee('Aug 2 – 8, 2026')
        ->call('jumpToToday')
        ->assertSee('Aug 9 – 15, 2026');
});

it('shows one chosen day when a day is opened', function () {
    userWithOneCalendar();
    fakeCalendar([[
        'id' => 'w1',
        'summary' => 'Dentist',
        'start' => ['dateTime' => '2026-08-11T09:00:00-05:00'],
        'end' => ['dateTime' => '2026-08-11T09:45:00-05:00'],
    ]]);

    Livewire::test(Calendar::class)
        ->call('openDay', '2026-08-11')
        ->assertSet('view', 'day')
        ->assertSee('Tuesday, August 11, 2026')
        ->assertSee('Dentist')
        ->assertSee('9:00 AM');
});

it('says so when a day is empty', function () {
    userWithOneCalendar();
    fakeCalendar();

    Livewire::test(Calendar::class)
        ->call('setView', 'day')
        ->assertSee('Nothing on your calendars this day.');
});

it('draws the month as whole weeks around it', function () {
    userWithOneCalendar();
    fakeCalendar();

    Livewire::test(Calendar::class)
        ->call('setView', 'month')
        ->assertSee('August 2026');

    // August 2026 starts on a Saturday and ends on a Monday, so the grid runs
    // from Sunday July 26 to Saturday September 5.
    Http::assertSent(fn (Request $request) => str_contains(urldecode($request->url()), 'timeMin=2026-07-26T05:00:00+00:00')
        && str_contains(urldecode($request->url()), 'timeMax=2026-09-06T04:59:59+00:00'));
});

it('repeats a multi-day event on every day it covers', function () {
    userWithOneCalendar();
    fakeCalendar([[
        'id' => 'a1',
        'summary' => 'Kristin away',
        // Google ends an all-day event on the following midnight.
        'start' => ['date' => '2026-08-11'],
        'end' => ['date' => '2026-08-14'],
    ]]);

    $component = Livewire::test(Calendar::class);

    // Tuesday through Thursday, but not Friday.
    foreach (['2026-08-11', '2026-08-12', '2026-08-13'] as $date) {
        $component->assertSee('week-'.md5($date.'primary'.'a1'), escape: false);
    }

    $component->assertDontSee('week-'.md5('2026-08-14primarya1'), escape: false);
});

it('keeps a meeting on the day it started when it ends at midnight', function () {
    userWithOneCalendar();
    fakeCalendar([[
        'id' => 'l1',
        'summary' => 'Late show',
        'start' => ['dateTime' => '2026-08-11T22:00:00-05:00'],
        'end' => ['dateTime' => '2026-08-12T00:00:00-05:00'],
    ]]);

    Livewire::test(Calendar::class)
        ->assertSee('week-'.md5('2026-08-11primaryl1'), escape: false)
        ->assertDontSee('week-'.md5('2026-08-12primaryl1'), escape: false);
});

it('points at the booking settings when nothing is connected', function () {
    Http::preventStrayRequests();
    loginUser();

    Livewire::test(Calendar::class)->assertSee('No calendars are connected yet.');

    Http::assertNothingSent();
});

it('reads the day from the url', function () {
    userWithOneCalendar();
    fakeCalendar();

    Livewire::withQueryParams(['view' => 'day', 'date' => '2026-09-02'])
        ->test(Calendar::class)
        ->assertSee('Wednesday, September 2, 2026');
});
