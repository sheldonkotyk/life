<?php

use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\TodoList;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-08-19 15:00:00 UTC');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('returns today\'s meals, members and unplanned slots', function () {
    $user = loginApiUser();
    $me = FamilyMember::create([
        'household_id' => $user->household_id,
        'user_id' => $user->id,
        'name' => 'Me',
    ]);
    $recipe = Recipe::create(['household_id' => $user->household_id, 'name' => 'Tacos', 'servings' => 4, 'prep_minutes' => 25]);
    $plan = MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-08-19',
        'slot' => 'dinner',
        'recipe_id' => $recipe->id,
    ]);
    $plan->attendees()->sync([$me->id => ['status' => 'eating']]);

    $response = $this->getJson('/api/today')->assertOk();

    expect($response->json('date'))->toBe('2026-08-19')
        ->and($response->json('my_family_member_id'))->toBe($me->id)
        ->and($response->json('meals.0.display_name'))->toBe('Tacos')
        ->and($response->json('meals.0.prep_minutes'))->toBe(25)
        ->and($response->json('meals.0.confirmed_count'))->toBe(1)
        ->and($response->json('unplanned_slots'))->toBe(['breakfast', 'lunch']);
});

it('reads the date from the query when one is given', function () {
    loginApiUser();

    $this->getJson('/api/today?date=2026-09-01')
        ->assertOk()
        ->assertJsonPath('date', '2026-09-01');
});

it('scales macros by how many people confirmed', function () {
    $user = loginApiUser();
    $a = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'A']);
    $b = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'B']);
    $recipe = Recipe::create(['household_id' => $user->household_id, 'name' => 'Chili', 'servings' => 2]);
    RecipeIngredient::create([
        'recipe_id' => $recipe->id,
        'name' => 'beans',
        'calories' => 200,
        'protein_g' => 20,
        'carbs_g' => 30,
        'fat_g' => 4,
        'sort_order' => 0,
    ]);
    $plan = MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-08-19',
        'slot' => 'dinner',
        'recipe_id' => $recipe->id,
    ]);
    $plan->attendees()->sync([
        $a->id => ['status' => 'eating'],
        $b->id => ['status' => 'not_eating'],
    ]);

    $response = $this->getJson('/api/today')->assertOk();

    expect((float) $response->json('meals.0.per_serving.calories'))->toBe(100.0)
        ->and((float) $response->json('meals.0.scaled_macros.calories'))->toBe(100.0)
        ->and($response->json('meals.0.confirmed_count'))->toBe(1);
});

it('lists jobs due today and anything assigned to me', function () {
    $user = loginApiUser();
    $me = FamilyMember::create([
        'household_id' => $user->household_id,
        'user_id' => $user->id,
        'name' => 'Me',
    ]);
    $list = TodoList::create(['household_id' => $user->household_id, 'name' => 'Chores']);

    $due = $list->items()->create(['title' => 'Bins', 'due_date' => '2026-08-19']);
    $mine = $list->items()->create(['title' => 'Someday', 'due_date' => '2027-01-01']);
    $mine->assignees()->sync([$me->id]);
    $list->items()->create(['title' => 'Later, not mine', 'due_date' => '2027-01-01']);
    $list->items()->create(['title' => 'Done', 'due_date' => '2026-08-19', 'completed_at' => now()]);

    $titles = collect($this->getJson('/api/today')->assertOk()->json('todos'))->pluck('title')->all();

    expect($titles)->toContain('Bins')
        ->toContain('Someday')
        ->not->toContain('Later, not mine')
        ->not->toContain('Done');
});

it('suggests unclaimed leftovers from the last three days', function () {
    $user = loginApiUser();
    $recipe = Recipe::create(['household_id' => $user->household_id, 'name' => 'Stew', 'servings' => 4]);

    MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-08-18',
        'slot' => 'dinner',
        'recipe_id' => $recipe->id,
        'save_leftovers' => true,
        'leftover_servings' => 2,
    ]);
    MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-08-10',
        'slot' => 'dinner',
        'recipe_id' => $recipe->id,
        'save_leftovers' => true,
    ]);

    $leftovers = $this->getJson('/api/today')->assertOk()->json('leftovers');

    expect($leftovers)->toHaveCount(1)
        ->and($leftovers[0]['name'])->toBe('Stew')
        ->and($leftovers[0]['servings'])->toBe(2);
});

it('keeps another household out of today', function () {
    loginApiUser();
    $other = Household::create(['name' => 'Other']);
    MealPlan::create(['household_id' => $other->id, 'date' => '2026-08-19', 'slot' => 'dinner', 'custom_name' => 'Theirs']);

    expect($this->getJson('/api/today')->assertOk()->json('meals'))->toBe([]);
});

it('sets my meal status without naming me', function () {
    $user = loginApiUser();
    $me = FamilyMember::create([
        'household_id' => $user->household_id,
        'user_id' => $user->id,
        'name' => 'Me',
    ]);
    $plan = MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-08-19',
        'slot' => 'dinner',
        'custom_name' => 'Pizza',
    ]);

    $this->postJson("/api/meal-plans/{$plan->id}/attendance", ['status' => 'running_late'])
        ->assertOk()
        ->assertJsonPath('late_count', 1);

    expect($plan->attendees()->first()->pivot->status)->toBe('running_late')
        ->and($plan->attendees()->first()->id)->toBe($me->id);
});

it('refuses a status that is not a status', function () {
    $user = loginApiUser();
    FamilyMember::create(['household_id' => $user->household_id, 'user_id' => $user->id, 'name' => 'Me']);
    $plan = MealPlan::create(['household_id' => $user->household_id, 'date' => '2026-08-19', 'slot' => 'dinner']);

    $this->postJson("/api/meal-plans/{$plan->id}/attendance", ['status' => 'maybe'])->assertStatus(422);
});

it('moves a meal to another day and forgets its old time', function () {
    $user = loginApiUser();
    $plan = MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-08-19',
        'slot' => 'dinner',
        'start_time' => '18:00',
    ]);

    $this->postJson("/api/meal-plans/{$plan->id}/move", ['date' => '2026-08-21', 'slot' => 'lunch'])
        ->assertOk()
        ->assertJsonPath('date', '2026-08-21')
        ->assertJsonPath('slot', 'lunch');

    expect($plan->fresh()->start_time)->toBeNull();
});
