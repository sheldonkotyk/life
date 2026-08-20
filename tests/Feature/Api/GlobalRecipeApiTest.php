<?php

use App\Models\GlobalRecipe;
use App\Models\GlobalRecipeIngredient;
use App\Models\Recipe;
use Illuminate\Support\Facades\Http;

function globalRecipe(string $name, string $category = 'Beef', string $area = 'Canadian'): GlobalRecipe
{
    $recipe = GlobalRecipe::create([
        'source' => 'themealdb',
        'external_id' => (string) random_int(1000, 9999),
        'name' => $name,
        'category' => $category,
        'area' => $area,
        'instructions' => 'Cook it.',
        'image_url' => 'https://example.test/'.$name.'.jpg',
    ]);
    GlobalRecipeIngredient::create([
        'global_recipe_id' => $recipe->id,
        'name' => 'beef',
        'measure' => '500g',
        'sort_order' => 0,
    ]);

    return $recipe;
}

it('pages the library and lists its filters', function () {
    loginApiUser();
    globalRecipe('Stew');
    globalRecipe('Pie', 'Dessert', 'British');

    $response = $this->getJson('/api/global-recipes')->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('total'))->toBe(2)
        ->and($response->json('categories'))->toBe(['Beef', 'Dessert'])
        ->and($response->json('areas'))->toBe(['British', 'Canadian']);
});

it('searches by name', function () {
    loginApiUser();
    globalRecipe('Stew');
    globalRecipe('Pie');

    $names = collect($this->getJson('/api/global-recipes?q=ste')->assertOk()->json('data'))->pluck('name')->all();

    expect($names)->toBe(['Stew']);
});

it('filters by category', function () {
    loginApiUser();
    globalRecipe('Stew', 'Beef');
    globalRecipe('Pie', 'Dessert');

    $names = collect($this->getJson('/api/global-recipes?category=Dessert')->assertOk()->json('data'))->pluck('name')->all();

    expect($names)->toBe(['Pie']);
});

it('shows one library recipe with its ingredients', function () {
    loginApiUser();
    $recipe = globalRecipe('Stew');

    $this->getJson("/api/global-recipes/{$recipe->id}")
        ->assertOk()
        ->assertJsonPath('name', 'Stew')
        ->assertJsonPath('ingredients.0.name', 'beef')
        ->assertJsonPath('ingredients.0.measure', '500g');
});

it('copies a library recipe into the household', function () {
    $user = loginApiUser();
    $global = globalRecipe('Stew');

    $this->postJson("/api/global-recipes/{$global->id}/adopt")->assertStatus(201);

    $recipe = Recipe::where('household_id', $user->household_id)->first();
    expect($recipe->name)->toBe('Stew')
        ->and($recipe->description)->toBe('Canadian · Beef')
        ->and($recipe->ingredients->pluck('name')->all())->toBe(['beef'])
        ->and($recipe->ingredients->first()->quantity)->toBe('500g');
});

it('discovers meals we do not already hold', function () {
    loginApiUser();
    $existing = globalRecipe('Known');

    Http::fake(['*filter.php*' => Http::response(['meals' => [
        ['idMeal' => $existing->external_id, 'strMeal' => 'Known', 'strMealThumb' => 'x'],
        ['idMeal' => '55555', 'strMeal' => 'New One', 'strMealThumb' => 'y'],
    ]])]);

    $discovered = $this->postJson('/api/global-recipes/discover', ['ingredients' => ['beef']])
        ->assertOk()
        ->json('discovered');

    expect($discovered)->toHaveCount(1)->and($discovered[0]['strMeal'])->toBe('New One');
});

it('imports a discovered meal into the library', function () {
    loginApiUser();

    Http::fake(['*lookup.php*' => Http::response(['meals' => [[
        'idMeal' => '77777',
        'strMeal' => 'Imported',
        'strCategory' => 'Beef',
        'strArea' => 'Canadian',
        'strInstructions' => 'Cook.',
        'strIngredient1' => 'beef',
        'strMeasure1' => '1kg',
    ]]])]);

    $this->postJson('/api/global-recipes/import', ['external_id' => '77777'])
        ->assertStatus(201)
        ->assertJsonPath('name', 'Imported');

    expect(GlobalRecipe::where('external_id', '77777')->exists())->toBeTrue();
});

it('reports a meal that could not be found', function () {
    loginApiUser();
    Http::fake(['*lookup.php*' => Http::response(['meals' => null])]);

    $this->postJson('/api/global-recipes/import', ['external_id' => '000'])->assertStatus(422);
});
