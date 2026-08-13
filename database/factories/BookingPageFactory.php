<?php

namespace Database\Factories;

use App\Models\BookingPage;
use App\Models\GoogleCalendarConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingPage>
 */
class BookingPageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    /**
     * A page belongs to the Google account it books into, so fall back to the
     * owner's first connected account rather than leaving it orphaned.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (BookingPage $bookingPage): void {
            if ($bookingPage->google_calendar_connection_id) {
                return;
            }

            $connection = GoogleCalendarConnection::firstWhere('user_id', $bookingPage->user_id)
                ?? GoogleCalendarConnection::factory()->create(['user_id' => $bookingPage->user_id]);

            $bookingPage->forceFill(['google_calendar_connection_id' => $connection->id])->save();
        });
    }

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'slug' => fake()->unique()->slug(2),
            'is_enabled' => true,
            'title' => 'Meet with '.fake()->firstName(),
            'description' => fake()->sentence(),
            'duration_minutes' => 30,
            'minimum_notice_hours' => 2,
            'buffer_minutes' => 0,
            'timezone' => 'America/Winnipeg',
            'availability_starts_at' => '09:00',
            'availability_ends_at' => '17:00',
            'available_days' => [1, 2, 3, 4, 5],
        ];
    }
}
