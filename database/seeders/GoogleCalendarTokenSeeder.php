<?php

namespace Database\Seeders;

use App\Models\GoogleCalendarToken;
use Illuminate\Database\Seeder;

class GoogleCalendarTokenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        GoogleCalendarToken::factory()->create();
    }
}
