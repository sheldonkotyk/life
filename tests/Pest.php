<?php

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

function loginUser(?Household $household = null): User
{
    $household ??= Household::create(['name' => 'Test House']);
    $user = User::create([
        'household_id' => $household->id,
        'name' => 'Test User',
        'email' => 'user-' . uniqid() . '@example.test',
    ]);
    test()->actingAs($user);
    return $user;
}
