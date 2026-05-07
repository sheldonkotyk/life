<?php

use App\Livewire\Tonight;
use App\Models\FamilyMember;
use App\Models\MealPlan;
use App\Models\Recipe;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

function tonightDinner(int $householdId, int $servings = 4, ?int $prep = 30): MealPlan
{
    $recipe = Recipe::create([
        'household_id' => $householdId,
        'name' => 'Stir Fry',
        'servings' => $servings,
        'prep_minutes' => $prep,
    ]);

    return MealPlan::create([
        'household_id' => $householdId,
        'date' => CarbonImmutable::today()->toDateString(),
        'slot' => 'dinner',
        'recipe_id' => $recipe->id,
    ]);
}

it('renders tonight dashboard with no plan', function () {
    loginUser();

    Livewire::test(Tonight::class)
        ->assertSee('Nothing planned today');
});

it('shows tonight dinner with confirmed count', function () {
    $user = loginUser();
    $plan = tonightDinner($user->household_id);
    $alex = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Alex']);
    $sam = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Sam']);
    $plan->attendees()->attach([$alex->id => ['status' => 'eating'], $sam->id => ['status' => 'eating']]);

    Livewire::test(Tonight::class)
        ->assertSee('Stir Fry')
        ->assertSee('2 confirmed');
});

it('lets the logged-in user mark themselves running late', function () {
    $user = loginUser();
    $plan = tonightDinner($user->household_id);
    $me = FamilyMember::create([
        'household_id' => $user->household_id,
        'user_id' => $user->id,
        'name' => 'Me',
    ]);

    Livewire::test(Tonight::class)
        ->call('setMyStatus', $plan->id, 'running_late');

    expect($plan->attendees()->where('family_members.id', $me->id)->first()?->pivot->status)
        ->toBe('running_late');
});

it('counts running-late attendees as confirmed', function () {
    $user = loginUser();
    $plan = tonightDinner($user->household_id);
    $alex = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Alex']);
    $sam = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Sam']);
    $plan->attendees()->attach([
        $alex->id => ['status' => 'eating'],
        $sam->id => ['status' => 'running_late'],
    ]);

    Livewire::test(Tonight::class)
        ->assertSee('2 confirmed')
        ->assertSee('1 running late');
});

it('rejects unknown attendance statuses', function () {
    $user = loginUser();
    $plan = tonightDinner($user->household_id);
    FamilyMember::create([
        'household_id' => $user->household_id,
        'user_id' => $user->id,
        'name' => 'Me',
    ]);

    Livewire::test(Tonight::class)
        ->call('setMyStatus', $plan->id, 'teleporting');

    expect($plan->attendees()->count())->toBe(0);
});

it('shows leftover reminder for unconsumed save_leftovers in past 3 days', function () {
    $user = loginUser();
    $recipe = Recipe::create(['household_id' => $user->household_id, 'name' => 'Chili', 'servings' => 6]);
    MealPlan::create([
        'household_id' => $user->household_id,
        'date' => CarbonImmutable::yesterday()->toDateString(),
        'slot' => 'dinner',
        'recipe_id' => $recipe->id,
        'save_leftovers' => true,
        'leftover_servings' => 2,
    ]);

    Livewire::test(Tonight::class)
        ->assertSee('Use it up')
        ->assertSee('Chili');
});
