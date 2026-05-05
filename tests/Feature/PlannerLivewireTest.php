<?php

use App\Livewire\Planner;
use App\Models\FamilyMember;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Livewire\Livewire;

function makeRecipeWith(int $householdId, array $ingredients, int $servings = 4): Recipe
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

it('excludes unavailable members from default attendees on a fresh slot', function () {
    $user = loginUser();
    $available = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'In']);
    $unavailable = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Out']);
    \App\Models\FamilyMemberUnavailability::create([
        'family_member_id' => $unavailable->id,
        'date' => '2026-05-04',
        'slot' => 'dinner',
    ]);

    $component = \Livewire\Livewire::test(\App\Livewire\Planner::class)
        ->call('openSlot', '2026-05-04', 'dinner');

    expect($component->get('attendees'))
        ->toContain($available->id)
        ->not->toContain($unavailable->id);
});

it('saves a meal plan with skipped ingredients', function () {
    $user = loginUser();
    $recipe = makeRecipeWith($user->household_id, [
        ['name' => 'salad', 'calories' => 200, 'protein_g' => 5, 'carbs_g' => 10, 'fat_g' => 8],
        ['name' => 'croutons', 'calories' => 400, 'protein_g' => 8, 'carbs_g' => 60, 'fat_g' => 12],
    ]);
    $member = FamilyMember::create([
        'household_id' => $user->household_id,
        'name' => 'Alex',
    ]);
    $croutons = $recipe->ingredients->firstWhere('name', 'croutons');

    Livewire::test(Planner::class)
        ->call('openSlot', '2026-05-04', 'dinner')
        ->set('selectedRecipeId', $recipe->id)
        ->set('attendees', [$member->id])
        ->set('skippedIngredientIds', [$croutons->id])
        ->call('savePlan');

    $plan = MealPlan::first();
    expect($plan->skippedIngredients->pluck('id')->all())->toBe([$croutons->id]);
    expect($plan->fresh('recipe.ingredients', 'skippedIngredients')->macrosPerServing()['calories'])
        ->toBe(50.0); // 200 / 4
});

it('hydrates skipped ingredients when reopening a meal plan', function () {
    $user = loginUser();
    $recipe = makeRecipeWith($user->household_id, [
        ['name' => 'a', 'calories' => 100],
        ['name' => 'b', 'calories' => 200],
    ]);
    $plan = MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-05-04',
        'slot' => 'dinner',
        'recipe_id' => $recipe->id,
    ]);
    $b = $recipe->ingredients->firstWhere('name', 'b');
    $plan->skippedIngredients()->attach($b->id);

    Livewire::test(Planner::class)
        ->call('openSlot', '2026-05-04', 'dinner', $plan->id)
        ->assertSet('skippedIngredientIds', [$b->id]);
});

it('ignores skipped-ingredient ids that do not belong to the chosen recipe', function () {
    $user = loginUser();
    $recipeA = makeRecipeWith($user->household_id, [['name' => 'a', 'calories' => 100]]);
    $recipeB = makeRecipeWith($user->household_id, [['name' => 'b', 'calories' => 200]]);
    $foreignIng = $recipeB->ingredients->first();

    Livewire::test(Planner::class)
        ->call('openSlot', '2026-05-04', 'dinner')
        ->set('selectedRecipeId', $recipeA->id)
        ->set('skippedIngredientIds', [$foreignIng->id])
        ->call('savePlan');

    $plan = MealPlan::first();
    expect($plan->skippedIngredients)->toHaveCount(0);
});
