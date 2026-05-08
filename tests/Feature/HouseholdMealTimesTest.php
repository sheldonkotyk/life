<?php

use App\Livewire\HouseholdMealTimes;
use App\Models\Household;
use App\Models\MealPlan;
use App\Models\User;
use Livewire\Livewire;

it('seeds new households with default meal times', function () {
    $household = Household::create(['name' => 'House']);

    expect(substr($household->fresh()->breakfast_start_time, 0, 5))->toBe('07:00')
        ->and(substr($household->fresh()->dinner_end_time, 0, 5))->toBe('19:30');
});

it('admin can save default meal times', function () {
    $user = loginUser();
    $user->households()->updateExistingPivot($user->household_id, ['role' => 'admin']);

    Livewire::test(HouseholdMealTimes::class)
        ->set('breakfastStart', '08:00')
        ->set('breakfastEnd', '09:30')
        ->set('lunchStart', '12:00')
        ->set('lunchEnd', '13:00')
        ->set('dinnerStart', '18:00')
        ->set('dinnerEnd', '20:00')
        ->call('save')
        ->assertHasNoErrors();

    $hh = $user->household->fresh();
    expect(substr($hh->breakfast_start_time, 0, 5))->toBe('08:00')
        ->and(substr($hh->dinner_end_time, 0, 5))->toBe('20:00');
});

it('rejects end time before start time', function () {
    $user = loginUser();
    $user->households()->updateExistingPivot($user->household_id, ['role' => 'admin']);

    Livewire::test(HouseholdMealTimes::class)
        ->set('breakfastStart', '09:00')
        ->set('breakfastEnd', '08:00')
        ->call('save')
        ->assertHasErrors(['breakfastEnd']);
});

it('non-admin cannot save meal times', function () {
    $household = Household::create(['name' => 'H']);
    $admin = User::create(['household_id' => $household->id, 'name' => 'A', 'email' => 'a-'.uniqid().'@x.test']);
    $admin->households()->syncWithoutDetaching([$household->id => ['role' => 'admin']]);

    loginUser($household);

    Livewire::test(HouseholdMealTimes::class)
        ->set('breakfastStart', '06:00')
        ->call('save');

    expect(substr($household->fresh()->breakfast_start_time, 0, 5))->toBe('07:00');
});

it('meal plan falls back to household default times', function () {
    $user = loginUser();
    $plan = MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-05-10',
        'slot' => 'dinner',
    ]);

    expect($plan->effectiveStartTime())->toBe('17:30')
        ->and($plan->effectiveEndTime())->toBe('19:30');
});

it('meal plan override beats household default', function () {
    $user = loginUser();
    $plan = MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-05-10',
        'slot' => 'dinner',
        'start_time' => '20:00',
        'end_time' => '21:30',
    ]);

    expect($plan->effectiveStartTime())->toBe('20:00')
        ->and($plan->effectiveEndTime())->toBe('21:30');
});
