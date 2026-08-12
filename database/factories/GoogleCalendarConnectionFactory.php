<?php

namespace Database\Factories;

use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleCalendarConnection>
 */
class GoogleCalendarConnectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'google_user_id' => fake()->uuid(),
            'google_email' => fake()->safeEmail(),
        ];
    }

    public function withToken(): static
    {
        return $this->afterCreating(function (GoogleCalendarConnection $connection): void {
            GoogleCalendarToken::factory()->for($connection, 'tokenable')->create();
        });
    }
}
