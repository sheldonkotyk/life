<?php

namespace Database\Seeders;

use App\Models\BookingCalendarSelection;
use Illuminate\Database\Seeder;

class BookingCalendarSelectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BookingCalendarSelection::factory()->count(3)->create();
    }
}
