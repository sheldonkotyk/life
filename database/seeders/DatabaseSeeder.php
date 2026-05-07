<?php

namespace Database\Seeders;

use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $hh = Household::create(['name' => 'The Kotyks']);

        $people = [
            ['name' => 'Sheldon', 'email' => 'sheldon@kotyk.com', 'role' => 'admin', 'color' => '#4f46e5'],
            ['name' => 'Julie', 'email' => 'julie@kotyk.com', 'role' => 'admin', 'color' => '#db2777'],
            ['name' => 'Kristin', 'email' => 'kristin@kotyk.com', 'role' => 'member', 'color' => '#0ea5e9'],
            ['name' => 'Ava', 'email' => 'ava@kotyk.com', 'role' => 'member', 'color' => '#16a34a'],
            ['name' => 'Jamie', 'email' => 'jamie@kotyk.com', 'role' => 'member', 'color' => '#f59e0b'],
        ];

        foreach ($people as $p) {
            $user = User::create([
                'name' => $p['name'],
                'email' => $p['email'],
            ]);
            $user->joinHousehold($hh, $p['role']);

            FamilyMember::create([
                'household_id' => $hh->id,
                'user_id' => $user->id,
                'name' => $p['name'],
                'color' => $p['color'],
            ]);
        }
    }
}
