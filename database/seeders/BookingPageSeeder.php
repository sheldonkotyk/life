<?php

namespace Database\Seeders;

use App\Models\BookingPage;
use Illuminate\Database\Seeder;

class BookingPageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BookingPage::factory()->count(3)->create();
    }
}
