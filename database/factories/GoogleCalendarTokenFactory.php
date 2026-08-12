<?php

namespace Database\Factories;

use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarToken;
use App\TokenProvider;
use CleaniqueCoders\TokenVault\Enums\Type;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleCalendarToken>
 */
class GoogleCalendarTokenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tokenable_type' => (new GoogleCalendarConnection)->getMorphClass(),
            'tokenable_id' => GoogleCalendarConnection::factory(),
            'provider' => TokenProvider::Google,
            'type' => Type::OAuthToken,
            'token' => json_encode([
                'access_token' => 'access-token-'.fake()->uuid(),
                'refresh_token' => 'refresh-token-'.fake()->uuid(),
            ], JSON_THROW_ON_ERROR),
            'meta' => [
                'scopes' => [
                    'https://www.googleapis.com/auth/calendar.calendarlist.readonly',
                    'https://www.googleapis.com/auth/calendar.events',
                    'https://www.googleapis.com/auth/calendar.freebusy',
                ],
                'last_four' => [
                    'access_token' => 'test',
                    'refresh_token' => 'test',
                ],
            ],
            'expires_at' => now()->addHour(),
        ];
    }
}
