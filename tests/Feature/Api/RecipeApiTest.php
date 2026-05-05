<?php

use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeMemberRating;

it('lists only this household\'s recipes', function () {
    $user = loginApiUser();
    Recipe::create(['household_id' => $user->household_id, 'name' => 'Mine', 'servings' => 4]);
    $other = Household::create(['name' => 'Other']);
    Recipe::create(['household_id' => $other->id, 'name' => 'Theirs', 'servings' => 2]);

    $response = $this->getJson('/api/recipes')->assertOk();
    expect(collect($response->json())->pluck('name')->all())->toBe(['Mine']);
});

it('shows a recipe with ingredients and ratings', function () {
    $user = loginApiUser();
    $recipe = Recipe::create(['household_id' => $user->household_id, 'name' => 'Stew', 'servings' => 4]);

    $this->getJson("/api/recipes/{$recipe->id}")
        ->assertOk()
        ->assertJsonPath('id', $recipe->id)
        ->assertJsonStructure(['id', 'ingredients', 'ratings']);
});

it('blocks viewing a recipe from another household', function () {
    loginApiUser();
    $other = Household::create(['name' => 'Other']);
    $recipe = Recipe::create(['household_id' => $other->id, 'name' => 'Hidden', 'servings' => 1]);

    $this->getJson("/api/recipes/{$recipe->id}")->assertStatus(403);
});

it('creates a recipe with ingredients and ratings', function () {
    $user = loginApiUser();
    $member = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Eater']);

    $this->postJson('/api/recipes', [
        'name' => 'Pasta',
        'servings' => 4,
        'ingredients' => [
            ['name' => 'noodles', 'quantity' => '200', 'unit' => 'g'],
            ['name' => 'sauce', 'quantity' => '1', 'unit' => 'jar'],
        ],
        'ratings' => [
            ['family_member_id' => $member->id, 'rating' => 'love'],
        ],
    ])->assertStatus(201);

    $recipe = Recipe::where('name', 'Pasta')->first();
    expect($recipe->ingredients)->toHaveCount(2)
        ->and($recipe->ratings)->toHaveCount(1);
});

it('drops ratings for members from another household', function () {
    $user = loginApiUser();
    $other = Household::create(['name' => 'Other']);
    $foreign = FamilyMember::create(['household_id' => $other->id, 'name' => 'NotMine']);

    $this->postJson('/api/recipes', [
        'name' => 'Soup',
        'servings' => 2,
        'ratings' => [['family_member_id' => $foreign->id, 'rating' => 'love']],
    ])->assertStatus(201);

    expect(RecipeMemberRating::count())->toBe(0);
});

it('replaces ingredients on update', function () {
    $user = loginApiUser();
    $recipe = Recipe::create(['household_id' => $user->household_id, 'name' => 'X', 'servings' => 2]);
    RecipeIngredient::create(['recipe_id' => $recipe->id, 'name' => 'old', 'sort_order' => 0]);

    $this->patchJson("/api/recipes/{$recipe->id}", [
        'name' => 'X',
        'servings' => 2,
        'ingredients' => [['name' => 'new']],
    ])->assertOk();

    $names = $recipe->fresh('ingredients')->ingredients->pluck('name')->all();
    expect($names)->toBe(['new']);
});

it('deletes a recipe', function () {
    $user = loginApiUser();
    $recipe = Recipe::create(['household_id' => $user->household_id, 'name' => 'Bye', 'servings' => 1]);

    $this->deleteJson("/api/recipes/{$recipe->id}")->assertOk();
    expect(Recipe::find($recipe->id))->toBeNull();
});

it('blocks deleting a recipe from another household', function () {
    loginApiUser();
    $other = Household::create(['name' => 'Other']);
    $recipe = Recipe::create(['household_id' => $other->id, 'name' => 'Theirs', 'servings' => 1]);

    $this->deleteJson("/api/recipes/{$recipe->id}")->assertStatus(403);
});
