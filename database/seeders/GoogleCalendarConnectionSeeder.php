<?php

namespace Database\Seeders;

use App\Models\GoogleCalendarConnection;
use Illuminate\Database\Seeder;

class GoogleCalendarConnectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        GoogleCalendarConnection::factory()->withToken()->create();
    }
}
