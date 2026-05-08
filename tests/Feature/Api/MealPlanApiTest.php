<?php

use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\RecipeIngredient;

it('lists meal plans within the default current week', function () {
    $user = loginApiUser();
    MealPlan::create([
        'household_id' => $user->household_id,
        'date' => now()->startOfWeek()->toDateString(),
        'slot' => 'dinner',
    ]);

    $response = $this->getJson('/api/meal-plans')->assertOk();
    expect($response->json())->toHaveCount(1);
});

it('lists meal plans within an explicit window', function () {
    $user = loginApiUser();
    MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-06-15',
        'slot' => 'lunch',
    ]);

    $this->getJson('/api/meal-plans?from=2026-06-01&to=2026-06-30')
        ->assertOk()
        ->assertJsonCount(1);
});

it('creates a meal plan with attendees', function () {
    $user = loginApiUser();
    $member = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'A']);
    $foreign = FamilyMember::create([
        'household_id' => Household::create(['name' => 'X'])->id,
        'name' => 'NotMine',
    ]);

    $response = $this->postJson('/api/meal-plans', [
        'date' => '2026-05-04',
        'slot' => 'dinner',
        'custom_name' => 'Pizza Night',
        'attendee_ids' => [$member->id, $foreign->id],
    ])->assertStatus(201);

    $plan = MealPlan::find($response->json('id'));
    expect($plan->attendees->pluck('id')->all())->toBe([$member->id]);
});

it('updates a meal plan and resyncs attendees', function () {
    $user = loginApiUser();
    $member = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'A']);
    $plan = MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-05-04',
        'slot' => 'dinner',
    ]);

    $this->patchJson("/api/meal-plans/{$plan->id}", [
        'date' => '2026-05-05',
        'slot' => 'lunch',
        'attendee_ids' => [$member->id],
    ])->assertOk();

    expect($plan->fresh()->slot)->toBe('lunch')
        ->and($plan->fresh()->attendees->pluck('id')->all())->toBe([$member->id]);
});

it('deletes a meal plan', function () {
    $user = loginApiUser();
    $plan = MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-05-04',
        'slot' => 'dinner',
    ]);

    $this->deleteJson("/api/meal-plans/{$plan->id}")->assertOk();
    expect(MealPlan::find($plan->id))->toBeNull();
});

it('blocks updating a meal plan from another household', function () {
    loginApiUser();
    $other = Household::create(['name' => 'Other']);
    $plan = MealPlan::create([
        'household_id' => $other->id,
        'date' => '2026-05-04',
        'slot' => 'dinner',
    ]);

    $this->patchJson("/api/meal-plans/{$plan->id}", [
        'date' => '2026-05-05',
        'slot' => 'dinner',
    ])->assertStatus(403);
});

it('aggregates the shopping list scaled by attendees and servings', function () {
    $user = loginApiUser();
    $a = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'A']);
    $b = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'B']);
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
    RecipeIngredient::create([
        'recipe_id' => $recipe->id,
        'name' => 'salt',
        'quantity' => 'pinch',
        'sort_order' => 1,
    ]);
    $plan = MealPlan::create([
        'household_id' => $user->household_id,
        'date' => now()->startOfWeek()->toDateString(),
        'slot' => 'dinner',
        'recipe_id' => $recipe->id,
    ]);
    $plan->attendees()->sync([$a->id, $b->id]);

    $items = $this->getJson('/api/shopping-list')->assertOk()->json();

    $noodles = collect($items)->firstWhere('name', 'noodles');
    $salt = collect($items)->firstWhere('name', 'salt');

    expect((float) $noodles['quantity'])->toBe(100.0)
        ->and($noodles['meals'])->toBe(['Pasta'])
        ->and((float) $salt['quantity'])->toBe(0.0)
        ->and($salt['notes'])->toBe(['pinch']);
});

it('excludes leftover meals from the shopping list', function () {
    $user = loginApiUser();
    $recipe = Recipe::create(['household_id' => $user->household_id, 'name' => 'X', 'servings' => 4]);
    RecipeIngredient::create([
        'recipe_id' => $recipe->id,
        'name' => 'rice',
        'quantity' => '100',
        'sort_order' => 0,
    ]);
    $base = MealPlan::create([
        'household_id' => $user->household_id,
        'date' => now()->startOfWeek()->toDateString(),
        'slot' => 'dinner',
        'recipe_id' => $recipe->id,
    ]);
    $leftoverPlan = MealPlan::create([
        'household_id' => $user->household_id,
        'date' => now()->startOfWeek()->addDay()->toDateString(),
        'slot' => 'lunch',
        'recipe_id' => $recipe->id,
    ]);
    $leftoverPlan->leftoverSources()->attach($base->id);

    $items = $this->getJson('/api/shopping-list')->assertOk()->json();
    expect(collect($items)->pluck('name')->all())->toBe(['rice'])
        ->and((float) $items[0]['quantity'])->toBe(25.0); // 1 eater / 4 servings * 100
});
