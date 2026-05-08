<?php

use App\Models\Household;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\RecipeIngredient;

function makeHousehold(): Household
{
    return Household::create(['name' => 'Test House']);
}

function makeRecipe(Household $hh, int $servings = 4, array $ingredients = []): Recipe
{
    $recipe = Recipe::create([
        'household_id' => $hh->id,
        'name' => 'Test Recipe',
        'servings' => $servings,
    ]);

    foreach ($ingredients as $i => $ing) {
        RecipeIngredient::create(array_merge([
            'recipe_id' => $recipe->id,
            'name' => $ing['name'] ?? "ing $i",
            'sort_order' => $i,
        ], $ing));
    }

    return $recipe->fresh('ingredients');
}

it('sums recipe ingredient macros for totals', function () {
    $hh = makeHousehold();
    $recipe = makeRecipe($hh, servings: 4, ingredients: [
        ['name' => 'chicken', 'calories' => 400, 'protein_g' => 80, 'carbs_g' => 0, 'fat_g' => 8],
        ['name' => 'rice', 'calories' => 600, 'protein_g' => 10, 'carbs_g' => 130, 'fat_g' => 2],
        ['name' => 'oil', 'calories' => 120, 'protein_g' => 0, 'carbs_g' => 0, 'fat_g' => 14],
    ]);

    expect($recipe->macroTotals())->toEqual([
        'calories' => 1120.0,
        'protein_g' => 90.0,
        'carbs_g' => 130.0,
        'fat_g' => 24.0,
    ]);
});

it('divides macro totals by servings for per-serving', function () {
    $hh = makeHousehold();
    $recipe = makeRecipe($hh, servings: 4, ingredients: [
        ['name' => 'a', 'calories' => 400, 'protein_g' => 80, 'carbs_g' => 100, 'fat_g' => 24],
    ]);

    expect($recipe->macrosPerServing())->toEqual([
        'calories' => 100.0,
        'protein_g' => 20.0,
        'carbs_g' => 25.0,
        'fat_g' => 6.0,
    ]);
});

it('treats null ingredient macros as zero', function () {
    $hh = makeHousehold();
    $recipe = makeRecipe($hh, servings: 2, ingredients: [
        ['name' => 'tracked', 'calories' => 200, 'protein_g' => 10, 'carbs_g' => 5, 'fat_g' => 3],
        ['name' => 'untracked'], // no macros
    ]);

    expect($recipe->macroTotals()['calories'])->toBe(200.0);
    expect($recipe->macrosPerServing()['calories'])->toBe(100.0);
});

it('avoids divide-by-zero when servings is 0', function () {
    $hh = makeHousehold();
    $recipe = makeRecipe($hh, servings: 0, ingredients: [
        ['name' => 'a', 'calories' => 100],
    ]);

    expect($recipe->macrosPerServing()['calories'])->toBe(100.0);
});

it('computes meal plan per-serving macros from recipe', function () {
    $hh = makeHousehold();
    $recipe = makeRecipe($hh, servings: 4, ingredients: [
        ['name' => 'a', 'calories' => 800, 'protein_g' => 40, 'carbs_g' => 80, 'fat_g' => 20],
    ]);

    $plan = MealPlan::create([
        'household_id' => $hh->id,
        'date' => '2026-05-04',
        'slot' => 'dinner',
        'recipe_id' => $recipe->id,
    ]);

    expect($plan->macrosPerServing())->toEqual([
        'calories' => 200.0,
        'protein_g' => 10.0,
        'carbs_g' => 20.0,
        'fat_g' => 5.0,
    ]);
});

it('excludes skipped ingredients from meal plan macros', function () {
    $hh = makeHousehold();
    $recipe = makeRecipe($hh, servings: 4, ingredients: [
        ['name' => 'salad', 'calories' => 200, 'protein_g' => 5, 'carbs_g' => 10, 'fat_g' => 8],
        ['name' => 'croutons', 'calories' => 400, 'protein_g' => 8, 'carbs_g' => 60, 'fat_g' => 12],
    ]);

    $plan = MealPlan::create([
        'household_id' => $hh->id,
        'date' => '2026-05-04',
        'slot' => 'dinner',
        'recipe_id' => $recipe->id,
    ]);

    $croutons = $recipe->ingredients->firstWhere('name', 'croutons');
    $plan->skippedIngredients()->attach($croutons->id);

    $macros = $plan->fresh('skippedIngredients', 'recipe.ingredients')->macrosPerServing();

    expect($macros['calories'])->toBe(50.0); // 200 / 4
    expect($macros['carbs_g'])->toBe(2.5);   // 10 / 4
});

it('returns zero macros for a meal plan without a recipe', function () {
    $hh = makeHousehold();
    $plan = MealPlan::create([
        'household_id' => $hh->id,
        'date' => '2026-05-04',
        'slot' => 'lunch',
        'custom_name' => 'Pizza night',
    ]);

    expect($plan->macrosPerServing())->toEqual([
        'calories' => 0.0,
        'protein_g' => 0.0,
        'carbs_g' => 0.0,
        'fat_g' => 0.0,
    ]);
});

it('uses the source recipe macros for leftover meal plans', function () {
    $hh = makeHousehold();
    $recipe = makeRecipe($hh, servings: 4, ingredients: [
        ['name' => 'a', 'calories' => 400, 'protein_g' => 40, 'carbs_g' => 0, 'fat_g' => 0],
    ]);

    $original = MealPlan::create([
        'household_id' => $hh->id,
        'date' => '2026-05-03',
        'slot' => 'dinner',
        'recipe_id' => $recipe->id,
        'save_leftovers' => true,
        'leftover_servings' => 2,
    ]);

    $leftover = MealPlan::create([
        'household_id' => $hh->id,
        'date' => '2026-05-04',
        'slot' => 'lunch',
    ]);
    $leftover->leftoverSources()->attach($original->id);

    expect($leftover->fresh('leftoverSources.recipe.ingredients', 'skippedIngredients')->macrosPerServing()['calories'])
        ->toBe(100.0);
});

it('rounds per-serving values to one decimal', function () {
    $hh = makeHousehold();
    $recipe = makeRecipe($hh, servings: 3, ingredients: [
        ['name' => 'a', 'protein_g' => 10],
    ]);

    expect($recipe->macrosPerServing()['protein_g'])->toBe(3.3);
});
