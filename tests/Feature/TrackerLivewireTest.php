<?php

use App\Livewire\Tracker;
use App\Models\FamilyMember;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Livewire\Livewire;

function recipeWith(int $householdId, array $ingredients, int $servings = 4): Recipe
{
    $recipe = Recipe::create([
        'household_id' => $householdId,
        'name' => 'R',
        'servings' => $servings,
    ]);
    foreach ($ingredients as $i => $ing) {
        RecipeIngredient::create(array_merge([
            'recipe_id' => $recipe->id,
            'sort_order' => $i,
        ], $ing));
    }
    return $recipe->fresh('ingredients');
}

it('rolls up consumed macros per attendee for the day', function () {
    $user = loginUser();
    $alex = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Alex']);
    $sam = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Sam']);

    $breakfast = recipeWith($user->household_id, [
        ['name' => 'oats', 'calories' => 800, 'protein_g' => 40, 'carbs_g' => 100, 'fat_g' => 20],
    ], servings: 4); // 200 kcal/serving

    $dinner = recipeWith($user->household_id, [
        ['name' => 'pasta', 'calories' => 1600, 'protein_g' => 80, 'carbs_g' => 200, 'fat_g' => 40],
    ], servings: 4); // 400 kcal/serving

    $b = MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-05-04', 'slot' => 'breakfast', 'recipe_id' => $breakfast->id,
    ]);
    $b->attendees()->attach([$alex->id, $sam->id]);

    $d = MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-05-04', 'slot' => 'dinner', 'recipe_id' => $dinner->id,
    ]);
    $d->attendees()->attach([$alex->id]); // Sam skipped dinner

    $component = Livewire::test(Tracker::class)->set('date', '2026-05-04');

    $consumed = $component->viewData('consumed');
    expect($consumed[$alex->id]['calories'])->toBe(600.0); // 200 + 400
    expect($consumed[$sam->id]['calories'])->toBe(200.0);  // breakfast only
});

it('excludes skipped ingredients from consumed totals', function () {
    $user = loginUser();
    $alex = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Alex']);

    $recipe = recipeWith($user->household_id, [
        ['name' => 'salad', 'calories' => 200, 'protein_g' => 5, 'carbs_g' => 10, 'fat_g' => 8],
        ['name' => 'croutons', 'calories' => 400, 'protein_g' => 8, 'carbs_g' => 60, 'fat_g' => 12],
    ], servings: 4);

    $plan = MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-05-04', 'slot' => 'lunch', 'recipe_id' => $recipe->id,
    ]);
    $plan->attendees()->attach($alex->id);
    $plan->skippedIngredients()->attach($recipe->ingredients->firstWhere('name', 'croutons')->id);

    $component = Livewire::test(Tracker::class)->set('date', '2026-05-04');
    expect($component->viewData('consumed')[$alex->id]['calories'])->toBe(50.0); // 200 / 4
});

it('returns zero macros for members with no meals on the day', function () {
    $user = loginUser();
    $alex = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Alex']);

    $component = Livewire::test(Tracker::class)->set('date', '2026-05-04');
    expect($component->viewData('consumed')[$alex->id])->toEqual([
        'calories' => 0.0, 'protein_g' => 0.0, 'carbs_g' => 0.0, 'fat_g' => 0.0,
    ]);
});

it('shifts the date forward and back', function () {
    loginUser();
    Livewire::test(Tracker::class)
        ->set('date', '2026-05-04')
        ->call('shiftDay', 1)
        ->assertSet('date', '2026-05-05')
        ->call('shiftDay', -2)
        ->assertSet('date', '2026-05-03');
});
