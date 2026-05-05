<?php

use App\Livewire\RecipeBrowser;
use App\Models\GlobalRecipe;
use App\Models\Recipe;
use App\Services\TheMealDbImporter;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('upserts a meal from TheMealDB payload', function () {
    $importer = new TheMealDbImporter();

    $recipe = $importer->upsertMeal([
        'idMeal' => '52772',
        'strMeal' => 'Teriyaki Chicken Casserole',
        'strCategory' => 'Chicken',
        'strArea' => 'Japanese',
        'strInstructions' => 'Step one. Step two.',
        'strMealThumb' => 'https://example.com/img.jpg',
        'strYoutube' => 'https://youtube.com/x',
        'strTags' => 'Meat,Casserole',
        'strIngredient1' => 'soy sauce',
        'strMeasure1' => '3/4 cup',
        'strIngredient2' => 'water',
        'strMeasure2' => '1/2 cup',
        'strIngredient3' => '',
    ]);

    expect($recipe->name)->toBe('Teriyaki Chicken Casserole')
        ->and($recipe->category)->toBe('Chicken')
        ->and($recipe->area)->toBe('Japanese')
        ->and($recipe->tags)->toBe(['Meat', 'Casserole'])
        ->and($recipe->ingredients)->toHaveCount(2);

    // Re-importing same meal updates instead of duplicating.
    $importer->upsertMeal([
        'idMeal' => '52772',
        'strMeal' => 'Teriyaki Chicken Casserole (v2)',
        'strIngredient1' => 'soy sauce',
        'strMeasure1' => '1 cup',
    ]);

    expect(GlobalRecipe::count())->toBe(1)
        ->and(GlobalRecipe::first()->name)->toBe('Teriyaki Chicken Casserole (v2)')
        ->and(GlobalRecipe::first()->ingredients)->toHaveCount(1);
});

it('searches global recipes by name, ingredient, area', function () {
    $a = GlobalRecipe::create(['name' => 'Sushi Rolls', 'area' => 'Japanese', 'category' => 'Seafood']);
    $a->ingredients()->create(['name' => 'nori', 'sort_order' => 1]);

    $b = GlobalRecipe::create(['name' => 'Beef Tacos', 'area' => 'Mexican', 'category' => 'Beef']);
    $b->ingredients()->create(['name' => 'tortilla', 'sort_order' => 1]);

    expect(GlobalRecipe::search('sushi')->pluck('id')->all())->toBe([$a->id]);
    expect(GlobalRecipe::search('mexican')->pluck('id')->all())->toBe([$b->id]);
    expect(GlobalRecipe::search('nori')->pluck('id')->all())->toBe([$a->id]);
});

it('imports a global recipe into the household', function () {
    $user = loginUser();

    $global = GlobalRecipe::create([
        'name' => 'Sushi Rolls', 'area' => 'Japanese', 'category' => 'Seafood',
        'instructions' => 'Roll it.',
    ]);
    $global->ingredients()->create(['name' => 'nori', 'measure' => '2 sheets', 'sort_order' => 1]);
    $global->ingredients()->create(['name' => 'rice', 'measure' => '1 cup', 'sort_order' => 2]);

    Livewire::test(RecipeBrowser::class)
        ->call('importToHousehold', $global->id)
        ->assertHasNoErrors();

    $recipe = Recipe::where('household_id', $user->household_id)->first();
    expect($recipe)->not->toBeNull()
        ->and($recipe->name)->toBe('Sushi Rolls')
        ->and($recipe->instructions)->toBe('Roll it.')
        ->and($recipe->ingredients)->toHaveCount(2)
        ->and($recipe->ingredients->first()->name)->toBe('nori')
        ->and($recipe->ingredients->first()->quantity)->toBe('2 sheets');
});

it('filters local catalog with AND across all ingredients', function () {
    $a = GlobalRecipe::create(['name' => 'Chicken Rice Bowl']);
    $a->ingredients()->createMany([
        ['name' => 'chicken breast', 'sort_order' => 1],
        ['name' => 'white rice', 'sort_order' => 2],
        ['name' => 'soy sauce', 'sort_order' => 3],
    ]);

    $b = GlobalRecipe::create(['name' => 'Chicken Soup']);
    $b->ingredients()->createMany([
        ['name' => 'chicken thighs', 'sort_order' => 1],
        ['name' => 'celery', 'sort_order' => 2],
    ]);

    expect(GlobalRecipe::withAllIngredients(['chicken'])->pluck('id')->all())
        ->toEqualCanonicalizing([$a->id, $b->id]);

    expect(GlobalRecipe::withAllIngredients(['chicken', 'rice'])->pluck('id')->all())
        ->toBe([$a->id]);

    expect(GlobalRecipe::withAllIngredients(['chicken', 'mushroom'])->pluck('id')->all())
        ->toBe([]);
});

it('discovers and imports meals via the v2 multi-ingredient filter', function () {
    Http::fake([
        '*/filter.php*' => Http::response([
            'meals' => [
                ['idMeal' => '111', 'strMeal' => 'Chicken Rice', 'strMealThumb' => 'http://x/y.jpg'],
                ['idMeal' => '222', 'strMeal' => 'Chicken Bowl', 'strMealThumb' => 'http://x/z.jpg'],
            ],
        ]),
        '*/lookup.php*' => Http::response([
            'meals' => [[
                'idMeal' => '111', 'strMeal' => 'Chicken Rice',
                'strCategory' => 'Chicken', 'strArea' => 'Asian',
                'strIngredient1' => 'chicken', 'strMeasure1' => '1 lb',
                'strIngredient2' => 'rice', 'strMeasure2' => '2 cups',
            ]],
        ]),
    ]);

    $importer = new TheMealDbImporter();

    $stubs = $importer->filterByIngredients(['chicken', 'rice']);
    expect($stubs)->toHaveCount(2);

    $recipe = $importer->importById('111');
    expect($recipe->name)->toBe('Chicken Rice')
        ->and($recipe->ingredients)->toHaveCount(2);
});

it('fetches meals by letter from the API', function () {
    Http::fake([
        '*/search.php*' => Http::response([
            'meals' => [['idMeal' => '1', 'strMeal' => 'Apple Pie']],
        ]),
    ]);

    $importer = new TheMealDbImporter();
    expect($importer->mealsByLetter('a'))->toHaveCount(1);
});
