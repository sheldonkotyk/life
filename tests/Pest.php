<?php

use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarToken;
use App\Models\Household;
use App\Models\User;
use App\TokenProvider;
use CleaniqueCoders\TokenVault\Enums\Type;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit', 'Browser');

function loginUser(?Household $household = null): User
{
    $household ??= Household::create(['name' => 'Test House']);
    $user = User::create([
        'household_id' => $household->id,
        'name' => 'Test User',
        'email' => 'user-'.uniqid().'@example.test',
    ]);
    $user->households()->syncWithoutDetaching([$household->id]);
    test()->actingAs($user);

    return $user;
}

function loginApiUser(?Household $household = null): User
{
    $household ??= Household::create(['name' => 'Test House']);
    $user = User::create([
        'household_id' => $household->id,
        'name' => 'API User',
        'email' => 'api-'.uniqid().'@example.test',
    ]);
    $user->households()->syncWithoutDetaching([$household->id]);
    Sanctum::actingAs($user);

    return $user;
}

function connectGoogleCalendar(
    User $user,
    string $email = 'calendar@example.test',
    string $accessToken = 'access-token',
    string $refreshToken = 'refresh-token',
    ?string $googleUserId = null,
): GoogleCalendarConnection {
    $connection = GoogleCalendarConnection::factory()->for($user)->create([
        'google_user_id' => $googleUserId ?? fake()->uuid(),
        'google_email' => $email,
    ]);
    $token = new GoogleCalendarToken([
        'provider' => TokenProvider::Google,
        'type' => Type::OAuthToken,
        'expires_at' => now()->addHour(),
    ]);
    $token->tokenable()->associate($connection);
    $token->setCredentials($accessToken, $refreshToken, [
        'https://www.googleapis.com/auth/calendar.calendarlist.readonly',
        'https://www.googleapis.com/auth/calendar.events',
        'https://www.googleapis.com/auth/calendar.freebusy',
    ]);
    $token->save();

    return $connection->load('oauthToken');
}

/**
 * Wait for something to become true, polling until it does. Browser tests drive
 * Livewire round trips whose timing varies with machine load, and a fixed sleep
 * either fails under load or wastes the difference.
 *
 * The condition is expected to give the browser a turn — sleeping alone starves
 * the requests it is waiting on.
 */
function waitUntil(callable $condition, int $timeoutMs = 8000, int $everyMs = 250): bool
{
    $deadline = microtime(true) + ($timeoutMs / 1000);

    do {
        if ($condition()) {
            return true;
        }

        usleep($everyMs * 1000);
    } while (microtime(true) < $deadline);

    return false;
}

function appleJwt(string $sub, array $extra = []): string
{
    $payload = base64_encode(json_encode(['sub' => $sub] + $extra));

    return 'header.'.rtrim(strtr($payload, '+/', '-_'), '=').'.sig';
}
