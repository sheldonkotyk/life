<?php

use App\Models\FamilyMember;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-08-19 15:00:00 UTC');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function trackedRecipe(int $householdId): Recipe
{
    $recipe = Recipe::create(['household_id' => $householdId, 'name' => 'Chili', 'servings' => 2]);
    RecipeIngredient::create([
        'recipe_id' => $recipe->id,
        'name' => 'beans',
        'calories' => 400,
        'protein_g' => 40,
        'carbs_g' => 60,
        'fat_g' => 8,
        'sort_order' => 0,
    ]);

    return $recipe;
}

it('adds up what each person ate on the day', function () {
    $user = loginApiUser();
    $member = FamilyMember::create([
        'household_id' => $user->household_id,
        'name' => 'Eater',
        'target_calories' => 2000,
    ]);
    $plan = MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-08-19',
        'slot' => 'dinner',
        'recipe_id' => trackedRecipe($user->household_id)->id,
    ]);
    $plan->attendees()->sync([$member->id]);

    $response = $this->getJson('/api/tracker?date=2026-08-19')->assertOk();

    expect($response->json('date'))->toBe('2026-08-19')
        ->and((float) $response->json('members.0.consumed.calories'))->toBe(200.0)
        ->and((float) $response->json('members.0.target_calories'))->toBe(2000.0)
        ->and($response->json('members.0.meals.0.name'))->toBe('Chili')
        ->and($response->json('members.0.meals.0.slot'))->toBe('dinner');
});

it('leaves someone who ate nothing on zero', function () {
    $user = loginApiUser();
    FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Faster']);

    $response = $this->getJson('/api/tracker?date=2026-08-19')->assertOk();

    expect((float) $response->json('members.0.consumed.calories'))->toBe(0.0)
        ->and($response->json('members.0.meals'))->toBe([]);
});

it('skips ingredients a meal left out', function () {
    $user = loginApiUser();
    $member = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Eater']);
    $recipe = trackedRecipe($user->household_id);
    $plan = MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-08-19',
        'slot' => 'dinner',
        'recipe_id' => $recipe->id,
    ]);
    $plan->attendees()->sync([$member->id]);
    $plan->skippedIngredients()->sync($recipe->ingredients->pluck('id')->all());

    expect((float) $this->getJson('/api/tracker?date=2026-08-19')->assertOk()->json('members.0.consumed.calories'))
        ->toBe(0.0);
});

it('hides a guest who has never eaten with us', function () {
    $user = loginApiUser();
    FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Member']);
    FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Guest', 'is_guest' => true]);

    $names = collect($this->getJson('/api/tracker?date=2026-08-19')->assertOk()->json('members'))
        ->pluck('name')->all();

    expect($names)->toBe(['Member']);
});
