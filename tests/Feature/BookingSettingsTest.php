<?php

use App\Livewire\BookingSettings;
use App\Models\BookingCalendarSelection;
use App\Models\BookingPage;
use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarToken;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('requires authentication to manage booking settings', function () {
    $this->get(route('booking.settings'))->assertRedirect(route('login'));
});

it('creates one private booking page per user', function () {
    $user = loginUser();

    Livewire::test(BookingSettings::class)
        ->assertSet('isEnabled', false)
        ->assertSee('Connect Google Calendar');

    $page = BookingPage::whereBelongsTo($user)->sole();

    expect($page->slug)->toBe('test-user')
        ->and($page->timezone)->toBe('UTC')
        ->and($page->available_days)->toBe([1, 2, 3, 4, 5]);
});

it('saves distinct calendar choices from multiple Google accounts', function () {
    Http::preventStrayRequests();
    $user = loginUser();
    $work = connectGoogleCalendar(
        $user,
        'work@example.test',
        'work-access-token',
        googleUserId: 'google-work',
    );
    $personal = connectGoogleCalendar(
        $user,
        'personal@example.test',
        'personal-access-token',
        googleUserId: 'google-personal',
    );

    Http::fake(function (Request $request) {
        $isWorkAccount = $request->hasHeader('Authorization', 'Bearer work-access-token');

        return Http::response([
            'items' => [[
                'id' => 'primary',
                'summary' => $isWorkAccount ? 'Work' : 'Personal',
                'primary' => true,
                'accessRole' => 'owner',
            ]],
        ]);
    });

    $workPrimary = hash('sha256', $work->id.'|primary');
    $personalPrimary = hash('sha256', $personal->id.'|primary');

    Livewire::test(BookingSettings::class)
        ->set('title', 'Coffee with me')
        ->set('slug', 'coffee-with-me')
        ->set('isEnabled', true)
        ->set('durationMinutes', 45)
        ->set('bufferMinutes', 15)
        ->set('bookingCalendarKey', $personalPrimary)
        ->set('availabilityCalendarKeys', [$workPrimary, $personalPrimary])
        ->call('save')
        ->assertHasNoErrors();

    $page = BookingPage::whereBelongsTo($user)->sole();

    expect($page->is_enabled)->toBeTrue()
        ->and($page->title)->toBe('Coffee with me')
        ->and($page->slug)->toBe('coffee-with-me')
        ->and($page->duration_minutes)->toBe(45)
        ->and($page->buffer_minutes)->toBe(15)
        ->and($page->calendarSelections)->toHaveCount(2)
        ->and($page->calendarSelections->pluck('google_calendar_id')->all())->toBe(['primary', 'primary'])
        ->and($page->bookingCalendarSelections()->sole()->google_calendar_connection_id)->toBe($personal->id)
        ->and($page->availabilityCalendarSelections()->pluck('google_calendar_connection_id')->sort()->values()->all())
        ->toBe(collect([$work->id, $personal->id])->sort()->values()->all());
});

it('shows safe Vault metadata without revealing credentials', function () {
    Http::preventStrayRequests();
    $user = loginUser();
    connectGoogleCalendar(
        $user,
        accessToken: 'secret-access-token-1234',
        refreshToken: 'secret-refresh-token-5678',
    );

    Http::fake([
        'www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response([
            'items' => [[
                'id' => 'primary',
                'summary' => 'Primary',
                'primary' => true,
                'accessRole' => 'owner',
            ]],
        ]),
    ]);

    Livewire::test(BookingSettings::class)
        ->assertSee('Google account details')
        ->assertSee('Vaulted OAuth credential')
        ->assertSee('••••••••1234')
        ->assertSee('••••••••5678')
        ->assertDontSee('secret-access-token-1234')
        ->assertDontSee('secret-refresh-token-5678');
});

it('does not allow a read only calendar to receive meetings', function () {
    Http::preventStrayRequests();
    $user = loginUser();
    $connection = connectGoogleCalendar($user);

    Http::fake([
        'www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response([
            'items' => [[
                'id' => 'readonly@example.test',
                'summary' => 'Read only',
                'primary' => true,
                'accessRole' => 'reader',
            ]],
        ]),
    ]);

    $readOnlyCalendar = hash('sha256', $connection->id.'|readonly@example.test');

    Livewire::test(BookingSettings::class)
        ->set('bookingCalendarKey', $readOnlyCalendar)
        ->set('availabilityCalendarKeys', [$readOnlyCalendar])
        ->call('save')
        ->assertHasErrors('bookingCalendarKey');
});

it('revalidates client supplied calendar data against Google before saving', function () {
    Http::preventStrayRequests();
    $user = loginUser();
    $connection = connectGoogleCalendar($user);

    Http::fake([
        'www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response([
            'items' => [[
                'id' => 'primary',
                'summary' => 'Primary',
                'primary' => true,
                'accessRole' => 'owner',
            ]],
        ]),
    ]);

    $forgedKey = hash('sha256', '999|forged-calendar');

    Livewire::test(BookingSettings::class)
        ->set('calendars', [[
            'key' => $forgedKey,
            'connection_id' => 999,
            'connection_email' => 'other@example.test',
            'id' => 'forged-calendar',
            'name' => 'Forged calendar',
            'primary' => true,
            'access_role' => 'owner',
        ]])
        ->set('bookingCalendarKey', $forgedKey)
        ->set('availabilityCalendarKeys', [$forgedKey])
        ->call('save')
        ->assertHasErrors('bookingCalendarKey');

    expect($connection->fresh())->not->toBeNull()
        ->and(BookingCalendarSelection::count())->toBe(0);
});

it('disconnects only the selected Google account and keeps a ready page published', function () {
    Http::preventStrayRequests();
    $user = loginUser();
    $work = connectGoogleCalendar($user, 'work@example.test', 'work-token');
    $personal = connectGoogleCalendar($user, 'personal@example.test', 'personal-token');
    $page = BookingPage::factory()->for($user)->create();
    BookingCalendarSelection::factory()->for($page)->for($work, 'connection')->create([
        'google_calendar_name' => 'Work',
    ]);
    BookingCalendarSelection::factory()->for($page)->for($personal, 'connection')->receivesBookings()->create([
        'google_calendar_name' => 'Personal',
    ]);
    $otherPage = BookingPage::factory()->create();

    Http::fake([
        'www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response([
            'items' => [[
                'id' => 'primary',
                'summary' => 'Primary',
                'primary' => true,
                'accessRole' => 'owner',
            ]],
        ]),
        'oauth2.googleapis.com/revoke' => Http::response(),
    ]);

    Livewire::test(BookingSettings::class)->call('disconnect', $work->id);

    expect($work->fresh())->toBeNull()
        ->and($personal->fresh())->not->toBeNull()
        ->and(GoogleCalendarToken::where('tokenable_id', $work->id)->exists())->toBeFalse()
        ->and(BookingCalendarSelection::whereBelongsTo($work, 'connection')->exists())->toBeFalse()
        ->and(BookingCalendarSelection::whereBelongsTo($personal, 'connection')->exists())->toBeTrue()
        ->and($page->fresh()->is_enabled)->toBeTrue()
        ->and($otherPage->fresh()->is_enabled)->toBeTrue();
});

it('unpublishes a booking page when its last Google account is disconnected', function () {
    Http::preventStrayRequests();
    $user = loginUser();
    $connection = connectGoogleCalendar($user);
    $page = BookingPage::factory()->for($user)->create();
    BookingCalendarSelection::factory()->for($page)->for($connection, 'connection')->receivesBookings()->create();

    Http::fake([
        'www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response([
            'items' => [[
                'id' => 'primary',
                'summary' => 'Primary',
                'primary' => true,
                'accessRole' => 'owner',
            ]],
        ]),
        'oauth2.googleapis.com/revoke' => Http::response(),
    ]);

    Livewire::test(BookingSettings::class)->call('disconnect', $connection->id);

    expect($page->fresh()->is_enabled)->toBeFalse()
        ->and(BookingCalendarSelection::count())->toBe(0)
        ->and(GoogleCalendarToken::count())->toBe(0);
});

it('cannot disconnect another users Google account', function () {
    Http::preventStrayRequests();
    $user = loginUser();
    $connection = connectGoogleCalendar($user);
    $otherConnection = connectGoogleCalendar(User::factory()->create(), 'other@example.test');

    Http::fake([
        'www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response(['items' => []]),
    ]);

    expect(fn () => Livewire::test(BookingSettings::class)
        ->call('disconnect', $otherConnection->id))
        ->toThrow(ModelNotFoundException::class);

    expect($connection->fresh())->not->toBeNull()
        ->and($otherConnection->fresh())->not->toBeNull();
});

it('removes vaulted credentials when their Life user is deleted', function () {
    $user = loginUser();
    connectGoogleCalendar($user, 'one@example.test');
    connectGoogleCalendar($user, 'two@example.test');

    expect(GoogleCalendarToken::count())->toBe(2);

    $user->delete();

    expect(GoogleCalendarConnection::count())->toBe(0)
        ->and(GoogleCalendarToken::count())->toBe(0);
});
