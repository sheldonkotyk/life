<?php

use App\Livewire\Availability;
use App\Livewire\ShoppingList;
use App\Livewire\Tracker;
use App\Models\Household;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

it('User::getTimezone defaults to UTC', function () {
    $h = Household::create(['name' => 'H']);
    $user = User::create(['household_id' => $h->id, 'name' => 'U', 'email' => 'u@example.test']);

    expect($user->getTimezone())->toBe('UTC');
});

it('User::getTimezone returns the stored value', function () {
    $h = Household::create(['name' => 'H']);
    $user = User::create([
        'household_id' => $h->id,
        'name' => 'U',
        'email' => 'u@example.test',
        'timezone' => 'America/Los_Angeles',
    ]);

    expect($user->getTimezone())->toBe('America/Los_Angeles');
});

it('Tracker mounts to the user\'s local date, not UTC', function () {
    // Freeze UTC at 2026-05-05 03:00 — that's 2026-05-04 20:00 in LA.
    CarbonImmutable::setTestNow('2026-05-05 03:00:00');

    $h = Household::create(['name' => 'H']);
    $user = User::create([
        'household_id' => $h->id,
        'name' => 'U',
        'email' => 'u@example.test',
        'timezone' => 'America/Los_Angeles',
    ]);
    $this->actingAs($user);

    Livewire::test(Tracker::class)->assertSet('date', '2026-05-04');
});

it('Availability days start on the user\'s local today', function () {
    CarbonImmutable::setTestNow('2026-05-05 03:00:00');

    $h = Household::create(['name' => 'H']);
    $user = User::create([
        'household_id' => $h->id,
        'name' => 'U',
        'email' => 'u@example.test',
        'timezone' => 'America/Los_Angeles',
    ]);
    $this->actingAs($user);

    $component = Livewire::test(Availability::class);
    $first = $component->instance()->days()[0];

    expect($first->toDateString())->toBe('2026-05-04');
});

it('ShoppingList weekStart uses the user\'s local week', function () {
    // Sunday UTC 03:00 == Saturday LA — and Carbon defaults week start to Monday,
    // so LA's startOfWeek for Sat 2026-05-02 is Mon 2026-04-27,
    // while UTC's startOfWeek for Sun 2026-05-03 would be Mon 2026-04-27 too.
    // Use a Monday-crossing case: UTC 03:00 Mon 2026-05-04 = LA Sun 2026-05-03.
    CarbonImmutable::setTestNow('2026-05-04 03:00:00');

    $h = Household::create(['name' => 'H']);
    $user = User::create([
        'household_id' => $h->id,
        'name' => 'U',
        'email' => 'u@example.test',
        'timezone' => 'America/Los_Angeles',
    ]);
    $this->actingAs($user);

    // LA: Sun 2026-05-03 → startOfWeek = Mon 2026-04-27
    // UTC: Mon 2026-05-04 → startOfWeek = Mon 2026-05-04
    Livewire::test(ShoppingList::class)->assertSet('weekStart', '2026-04-27');
});

it('MealPlan shopping-list endpoint honours user timezone for default window', function () {
    CarbonImmutable::setTestNow('2026-05-04 03:00:00');

    $h = Household::create(['name' => 'H']);
    $user = User::create([
        'household_id' => $h->id,
        'name' => 'U',
        'email' => 'u@example.test',
        'timezone' => 'America/Los_Angeles',
    ]);
    \Laravel\Sanctum\Sanctum::actingAs($user);

    // Plan exists in the LA-local week (starts 2026-04-27) but not in the UTC week (starts 2026-05-04).
    \App\Models\MealPlan::create([
        'household_id' => $h->id,
        'date' => '2026-04-30',
        'slot' => 'dinner',
    ]);

    $this->getJson('/api/meal-plans')->assertOk()->assertJsonCount(1);
});
