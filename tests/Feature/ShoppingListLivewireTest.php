<?php

use App\Livewire\ShoppingList;
use App\Models\FamilyMember;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

it('renders aggregated ingredients grouped by category', function () {
    $user = loginUser();
    $recipe = Recipe::create([
        'household_id' => $user->household_id,
        'name' => 'Pasta',
        'servings' => 4,
    ]);
    RecipeIngredient::create([
        'recipe_id' => $recipe->id,
        'name' => 'noodles',
        'quantity' => '200',
        'unit' => 'g',
        'category' => 'Pantry',
        'sort_order' => 0,
    ]);
    MealPlan::create([
        'household_id' => $user->household_id,
        'date' => CarbonImmutable::now()->startOfWeek()->toDateString(),
        'slot' => 'dinner',
        'recipe_id' => $recipe->id,
    ]);

    Livewire::test(ShoppingList::class)
        ->assertSee('Pantry')
        ->assertSee('noodles');
});

it('shifts the week forward and back', function () {
    loginUser();
    $start = CarbonImmutable::now()->startOfWeek()->toDateString();

    Livewire::test(ShoppingList::class)
        ->assertSet('weekStart', $start)
        ->call('shiftWeek', 1)
        ->assertSet('weekStart', CarbonImmutable::parse($start)->addWeek()->toDateString())
        ->call('shiftWeek', -1)
        ->assertSet('weekStart', $start);
});

it('scales ingredient quantities by attendees and servings', function () {
    $user = loginUser();
    $a = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'A']);
    $b = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'B']);
    $recipe = Recipe::create([
        'household_id' => $user->household_id,
        'name' => 'Stew',
        'servings' => 4,
    ]);
    RecipeIngredient::create([
        'recipe_id' => $recipe->id,
        'name' => 'beef',
        'quantity' => '400',
        'unit' => 'g',
        'category' => 'Meat',
        'sort_order' => 0,
    ]);
    $plan = MealPlan::create([
        'household_id' => $user->household_id,
        'date' => CarbonImmutable::now()->startOfWeek()->toDateString(),
        'slot' => 'dinner',
        'recipe_id' => $recipe->id,
    ]);
    $plan->attendees()->sync([$a->id, $b->id]);

    $component = Livewire::test(ShoppingList::class);
    $grouped = $component->viewData('grouped');
    $beef = collect($grouped['Meat'])->first();

    expect($beef['qty_total'])->toBe(200.0); // 2 eaters / 4 servings * 400
});
