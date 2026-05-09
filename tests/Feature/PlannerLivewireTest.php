<?php

use App\Livewire\Planner;
use App\Models\FamilyMember;
use App\Models\FamilyMemberUnavailability;
use App\Models\Household;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
    FamilyMemberUnavailability::create([
        'family_member_id' => $unavailable->id,
        'date' => '2026-05-04',
        'slot' => 'dinner',
    ]);

    $component = Livewire::test(Planner::class)
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

it('does not offer leftovers from the same date and slot being edited', function () {
    $user = loginUser();
    MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-05-07',
        'slot' => 'dinner',
        'custom_name' => 'Chuck Roast',
        'save_leftovers' => true,
        'leftover_servings' => 3,
    ]);
    MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-05-06',
        'slot' => 'dinner',
        'custom_name' => 'Tomato Soup',
        'save_leftovers' => true,
        'leftover_servings' => 2,
    ]);

    $component = Livewire::test(Planner::class)
        ->call('openSlot', '2026-05-07', 'dinner');

    $names = collect($component->instance()->availableLeftovers)->pluck('custom_name')->all();
    expect($names)->toContain('Tomato Soup')->not->toContain('Chuck Roast');
});

it('creates a recipe inline via createRecipeFromName and selects it', function () {
    $user = loginUser();

    Livewire::test(Planner::class)
        ->call('openSlot', '2026-05-04', 'dinner')
        ->set('newRecipeName', '  Chili  ')
        ->call('createRecipeFromName')
        ->assertSet('newRecipeName', '');

    $recipe = Recipe::where('household_id', $user->household_id)->firstWhere('name', 'Chili');
    expect($recipe)->not->toBeNull()
        ->and($recipe->servings)->toBe(4);
});

it('does not create a recipe when newRecipeName is blank', function () {
    $user = loginUser();

    Livewire::test(Planner::class)
        ->call('openSlot', '2026-05-04', 'dinner')
        ->set('newRecipeName', '   ')
        ->call('createRecipeFromName');

    expect(Recipe::where('household_id', $user->household_id)->count())->toBe(0);
});

it('moves a meal plan to a new date and slot via movePlan', function () {
    $user = loginUser();
    $plan = MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-05-04',
        'slot' => 'dinner',
        'custom_name' => 'Tacos',
        'start_time' => '18:00',
        'end_time' => '19:00',
    ]);

    Livewire::test(Planner::class)
        ->call('movePlan', $plan->id, '2026-05-06', 'lunch');

    $plan->refresh();
    expect($plan->date->toDateString())->toBe('2026-05-06')
        ->and($plan->slot)->toBe('lunch')
        ->and($plan->start_time)->toBeNull()
        ->and($plan->end_time)->toBeNull();
});

it('movePlan ignores invalid slot', function () {
    $user = loginUser();
    $plan = MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-05-04',
        'slot' => 'dinner',
        'custom_name' => 'Tacos',
    ]);

    Livewire::test(Planner::class)
        ->call('movePlan', $plan->id, '2026-05-06', 'midnight-snack');

    $plan->refresh();
    expect($plan->slot)->toBe('dinner')
        ->and($plan->date->toDateString())->toBe('2026-05-04');
});

it('movePlan refuses to move plans from a different household', function () {
    loginUser();
    $otherHousehold = Household::create(['name' => 'Other']);
    $plan = MealPlan::create([
        'household_id' => $otherHousehold->id,
        'date' => '2026-05-04',
        'slot' => 'dinner',
        'custom_name' => 'Foreign',
    ]);

    expect(fn () => Livewire::test(Planner::class)->call('movePlan', $plan->id, '2026-05-05', 'lunch'))
        ->toThrow(ModelNotFoundException::class);

    expect($plan->fresh()->date->toDateString())->toBe('2026-05-04');
});
