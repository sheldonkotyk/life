<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingPage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = CarbonImmutable::now()->addDays(2)->startOfHour();

        return [
            'booking_page_id' => BookingPage::factory(),
            'guest_name' => fake()->name(),
            'guest_email' => fake()->safeEmail(),
            'notes' => fake()->optional()->sentence(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(30),
            'guest_timezone' => 'America/Winnipeg',
            'status' => Booking::STATUS_CONFIRMED,
            'google_event_id' => 'lifebooking'.fake()->unique()->randomNumber(7),
            'google_calendar_id' => 'primary',
            'google_event_link' => 'https://calendar.google.com/calendar/event?eid='.fake()->uuid(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Booking::STATUS_CANCELLED,
            'cancelled_at' => CarbonImmutable::now(),
        ]);
    }
}
