<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GlobalRecipe;
use App\Models\GlobalRecipeIngredient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Services\TheMealDbImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The shared recipe library everyone browses before writing their own.
 */
class GlobalRecipeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $ingredients = (array) $request->query('ingredients', []);

        $recipes = GlobalRecipe::query()
            ->search((string) $request->query('q', ''))
            ->when($request->query('category'), fn ($query, $category) => $query->where('category', $category))
            ->when($request->query('area'), fn ($query, $area) => $query->where('area', $area))
            ->when($ingredients, fn ($query) => $query->withAllIngredients($ingredients))
            ->orderBy('name')
            ->paginate(min(50, max(1, (int) $request->query('per_page', 24))));

        return response()->json([
            'data' => collect($recipes->items())->map(fn (GlobalRecipe $recipe) => $this->summary($recipe))->all(),
            'current_page' => $recipes->currentPage(),
            'last_page' => $recipes->lastPage(),
            'total' => $recipes->total(),
            'categories' => GlobalRecipe::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'areas' => GlobalRecipe::query()->whereNotNull('area')->distinct()->orderBy('area')->pluck('area'),
        ]);
    }

    public function show(Request $request, GlobalRecipe $globalRecipe): JsonResponse
    {
        $globalRecipe->load('ingredients');

        return response()->json([
            ...$this->summary($globalRecipe),
            'instructions' => $globalRecipe->instructions,
            'youtube_url' => $globalRecipe->youtube_url,
            'source_url' => $globalRecipe->source_url,
            'ingredients' => $globalRecipe->ingredients->map(fn (GlobalRecipeIngredient $ingredient) => [
                'id' => $ingredient->id,
                'name' => $ingredient->name,
                'measure' => $ingredient->measure,
            ])->values()->all(),
        ]);
    }

    /**
     * Ask TheMealDB for meals made from these ingredients that we do not have yet.
     */
    public function discover(Request $request, TheMealDbImporter $importer): JsonResponse
    {
        $data = $request->validate([
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*' => ['string', 'max:80'],
        ]);

        $stubs = $importer->filterByIngredients($data['ingredients']);

        $existing = GlobalRecipe::where('source', 'themealdb')
            ->whereIn('external_id', array_column($stubs, 'idMeal'))
            ->pluck('external_id')
            ->all();

        return response()->json([
            'discovered' => array_values(array_filter(
                $stubs,
                fn (array $stub) => ! in_array($stub['idMeal'], $existing, true)
            )),
        ]);
    }

    public function import(Request $request, TheMealDbImporter $importer): JsonResponse
    {
        $data = $request->validate(['external_id' => ['required', 'string', 'max:50']]);

        $recipe = $importer->importById($data['external_id']);

        return response()->json($recipe ? $this->summary($recipe) : ['ok' => false], $recipe ? 201 : 422);
    }

    /**
     * Copy a library recipe into this household so it can be edited and planned.
     */
    public function adopt(Request $request, GlobalRecipe $globalRecipe): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->household_id, 403);

        $globalRecipe->load('ingredients');

        $recipe = DB::transaction(function () use ($globalRecipe, $user): Recipe {
            $recipe = Recipe::create([
                'household_id' => $user->household_id,
                'name' => $globalRecipe->name,
                'description' => $globalRecipe->category && $globalRecipe->area
                    ? "{$globalRecipe->area} · {$globalRecipe->category}"
                    : ($globalRecipe->category ?? $globalRecipe->area),
                'servings' => 4,
                'source_url' => $globalRecipe->source_url ?? $globalRecipe->youtube_url,
                'instructions' => $globalRecipe->instructions,
                'tags' => $globalRecipe->tags,
            ]);

            foreach ($globalRecipe->ingredients as $index => $ingredient) {
                RecipeIngredient::create([
                    'recipe_id' => $recipe->id,
                    'name' => $ingredient->name,
                    'quantity' => $ingredient->measure,
                    'sort_order' => $index,
                ]);
            }

            return $recipe;
        });

        return response()->json($recipe->load('ingredients', 'ratings'), 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(GlobalRecipe $recipe): array
    {
        return [
            'id' => $recipe->id,
            'name' => $recipe->name,
            'category' => $recipe->category,
            'area' => $recipe->area,
            'image_url' => $recipe->image_url,
            'tags' => $recipe->tags,
        ];
    }
}
