<?php

namespace Database\Factories;

use App\Models\BookingCalendarSelection;
use App\Models\BookingPage;
use App\Models\GoogleCalendarConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingCalendarSelection>
 */
class BookingCalendarSelectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_page_id' => BookingPage::factory(),
            'google_calendar_connection_id' => GoogleCalendarConnection::factory()->withToken(),
            'google_calendar_id' => 'primary',
            'google_calendar_name' => 'Primary calendar',
            'checks_conflicts' => true,
            'receives_bookings' => false,
        ];
    }

    public function receivesBookings(): static
    {
        return $this->state(fn (): array => ['receives_bookings' => true]);
    }
}
