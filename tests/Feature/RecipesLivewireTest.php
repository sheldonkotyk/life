<?php

use App\Livewire\Recipes;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Livewire\Livewire;

it('persists ingredient macros when saving a recipe', function () {
    $user = loginUser();

    Livewire::test(Recipes::class)
        ->call('startCreate')
        ->set('name', 'Chicken Bowl')
        ->set('servings', 4)
        ->set('ingredients', [
            ['name' => 'chicken', 'quantity' => '1', 'unit' => 'lb', 'category' => 'meat',
                'calories' => 800, 'protein_g' => 160, 'carbs_g' => 0, 'fat_g' => 16],
            ['name' => 'rice', 'quantity' => '2', 'unit' => 'cup', 'category' => 'grain',
                'calories' => 400, 'protein_g' => 8, 'carbs_g' => 88, 'fat_g' => 1],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $recipe = Recipe::where('household_id', $user->household_id)->first();
    expect($recipe)->not->toBeNull();
    expect($recipe->ingredients)->toHaveCount(2);

    $chicken = $recipe->ingredients->firstWhere('name', 'chicken');
    expect($chicken->calories)->toBe(800.0);
    expect($chicken->protein_g)->toBe(160.0);

    expect($recipe->macrosPerServing()['calories'])->toBe(300.0); // (800+400) / 4
});

it('hydrates ingredient macros when editing a recipe', function () {
    $user = loginUser();
    $recipe = Recipe::create([
        'household_id' => $user->household_id,
        'name' => 'Existing',
        'servings' => 2,
    ]);
    RecipeIngredient::create([
        'recipe_id' => $recipe->id,
        'name' => 'eggs',
        'calories' => 140,
        'protein_g' => 12,
        'carbs_g' => 1,
        'fat_g' => 10,
        'sort_order' => 0,
    ]);

    Livewire::test(Recipes::class)
        ->call('edit', $recipe->id)
        ->assertSet('ingredients.0.calories', 140.0)
        ->assertSet('ingredients.0.protein_g', 12.0);
});

it('saves ingredients with blank macro fields as null', function () {
    $user = loginUser();

    Livewire::test(Recipes::class)
        ->call('startCreate')
        ->set('name', 'Loose Recipe')
        ->set('ingredients', [
            ['name' => 'mystery', 'quantity' => '', 'unit' => '', 'category' => '',
                'calories' => '', 'protein_g' => '', 'carbs_g' => '', 'fat_g' => ''],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $ing = RecipeIngredient::first();
    expect($ing->calories)->toBeNull();
    expect($ing->protein_g)->toBeNull();
});
