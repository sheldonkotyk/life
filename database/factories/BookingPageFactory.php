<?php

namespace Database\Factories;

use App\Models\BookingPage;
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
