<?php

use App\Models\BookingPage;
use App\Models\GoogleCalendarToken;
use App\Services\GoogleCalendarClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

it('requires authentication to connect a Google calendar', function () {
    $this->get(route('google-calendar.redirect'))->assertRedirect(route('login'));
});

it('redirects to Google with offline access and an account chooser', function () {
    loginUser();
    config([
        'services.google.client_id' => 'client-id',
        'services.google.client_secret' => 'client-secret',
        'services.google.redirect' => 'https://life.test/auth/google/calendar/callback',
    ]);

    $response = $this->get(route('google-calendar.redirect'))->assertRedirect();
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($query['access_type'])->toBe('offline')
        ->and($query['prompt'])->toBe('select_account consent')
        ->and($query['include_granted_scopes'])->toBe('true')
        ->and($query['scope'])->toContain('calendar.freebusy')
        ->and($query['scope'])->toContain('calendar.events');
});

it('stores Google credentials in Token Vault after the callback', function () {
    $user = loginUser();
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-user-123',
        'email' => 'calendar-owner@example.test',
        'token' => 'plain-access-token-1234',
        'refreshToken' => 'plain-refresh-token-5678',
        'expiresIn' => 3600,
        'approvedScopes' => ['calendar.read', 'calendar.write'],
    ]));

    $this->get(route('google-calendar.callback'))
        ->assertRedirect(route('booking.settings'));

    $connection = $user->googleCalendarConnections()->with('oauthToken')->sole();
    $token = $connection->oauthToken;
    $rawToken = DB::table('token_vaults')->where('id', $token->id)->value('token');

    expect($connection->google_user_id)->toBe('google-user-123')
        ->and($token->accessToken())->toBe('plain-access-token-1234')
        ->and($token->refreshToken())->toBe('plain-refresh-token-5678')
        ->and($token->maskedAccessToken())->toBe('••••••••1234')
        ->and($token->maskedRefreshToken())->toBe('••••••••5678')
        ->and($token->toArray())->not->toHaveKey('token')
        ->and($rawToken)->not->toContain('plain-access-token')
        ->and($rawToken)->not->toContain('plain-refresh-token')
        ->and(BookingPage::whereBelongsTo($user)->exists())->toBeTrue();
});

it('keeps the Google profile picture so accounts are recognisable', function () {
    $user = loginUser();
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-user-123',
        'name' => 'Calendar Owner',
        'email' => 'calendar-owner@example.test',
        'avatar' => 'https://lh3.googleusercontent.com/a/photo',
        'token' => 'access-token',
        'refreshToken' => 'refresh-token',
        'expiresIn' => 3600,
    ]));

    $this->get(route('google-calendar.callback'))->assertRedirect(route('booking.settings'));

    $connection = $user->googleCalendarConnections()->sole();

    expect($connection->google_name)->toBe('Calendar Owner')
        ->and($connection->google_avatar_url)->toBe('https://lh3.googleusercontent.com/a/photo');

    // Reconnecting without profile details must not wipe the picture we have.
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-user-123',
        'name' => null,
        'email' => 'calendar-owner@example.test',
        'avatar' => null,
        'token' => 'newer-access-token',
        'refreshToken' => 'refresh-token',
        'expiresIn' => 3600,
    ]));

    $this->get(route('google-calendar.callback'))->assertRedirect(route('booking.settings'));

    expect($connection->fresh()->google_avatar_url)->toBe('https://lh3.googleusercontent.com/a/photo')
        ->and($connection->fresh()->google_name)->toBe('Calendar Owner');
});

it('allows one Life user to connect multiple Google accounts', function () {
    $user = loginUser();

    foreach ([
        ['id' => 'google-one', 'email' => 'one@example.test'],
        ['id' => 'google-two', 'email' => 'two@example.test'],
    ] as $googleIdentity) {
        Socialite::fake('google', SocialiteUser::fake([
            ...$googleIdentity,
            'token' => 'access-'.$googleIdentity['id'],
            'refreshToken' => 'refresh-'.$googleIdentity['id'],
            'expiresIn' => 3600,
        ]));

        $this->get(route('google-calendar.callback'))->assertRedirect(route('booking.settings'));
    }

    expect($user->googleCalendarConnections()->count())->toBe(2)
        ->and($user->googleCalendarConnections()->pluck('google_email')->sort()->values()->all())
        ->toBe(['one@example.test', 'two@example.test'])
        ->and(GoogleCalendarToken::count())->toBe(2);
});

it('reconnecting the same Google identity updates only that account and preserves its refresh token', function () {
    $user = loginUser();
    $first = connectGoogleCalendar(
        $user,
        'first@example.test',
        'first-access',
        'first-refresh',
        'google-first',
    );
    $second = connectGoogleCalendar(
        $user,
        'second@example.test',
        'second-access',
        'second-refresh',
        'google-second',
    );

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-first',
        'email' => 'first-renamed@example.test',
        'token' => 'new-first-access',
        'refreshToken' => null,
        'expiresIn' => 3600,
    ]));

    $this->get(route('google-calendar.callback'))->assertRedirect(route('booking.settings'));

    expect($user->googleCalendarConnections()->count())->toBe(2)
        ->and($first->fresh()->google_email)->toBe('first-renamed@example.test')
        ->and($first->fresh()->oauthToken->accessToken())->toBe('new-first-access')
        ->and($first->fresh()->oauthToken->refreshToken())->toBe('first-refresh')
        ->and($second->fresh()->oauthToken->accessToken())->toBe('second-access')
        ->and($second->fresh()->oauthToken->refreshToken())->toBe('second-refresh');
});

it('refreshes an expired vaulted token before loading calendars', function () {
    Http::preventStrayRequests();
    config([
        'services.google.client_id' => 'client-id',
        'services.google.client_secret' => 'client-secret',
    ]);

    $user = loginUser();
    $connection = connectGoogleCalendar($user, accessToken: 'expired-token', refreshToken: 'refresh-token');
    $connection->oauthToken->update(['expires_at' => now()->subMinute()]);

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'fresh-token',
            'expires_in' => 3600,
        ]),
        'www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response([
            'items' => [[
                'id' => 'primary',
                'summary' => 'My Calendar',
                'primary' => true,
                'accessRole' => 'owner',
            ]],
        ]),
    ]);

    $calendars = app(GoogleCalendarClient::class)->calendars($connection);

    expect($calendars)->toBe([[
        'id' => 'primary',
        'name' => 'My Calendar',
        'primary' => true,
        'access_role' => 'owner',
    ]])->and($connection->fresh()->oauthToken->accessToken())->toBe('fresh-token');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/token'
        && $request['grant_type'] === 'refresh_token');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/calendarList')
        && $request->hasHeader('Authorization', 'Bearer fresh-token'));
});
