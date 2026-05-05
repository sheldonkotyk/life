<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeMemberRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Recipe::where('household_id', $request->user()->household_id)
                ->with('ingredients', 'ratings')->orderBy('name')->get()
        );
    }

    public function show(Request $request, Recipe $recipe): JsonResponse
    {
        $this->authorize($request, $recipe);
        return response()->json($recipe->load('ingredients', 'ratings'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);
        $recipe = Recipe::create([...$this->scalarData($data), 'household_id' => $request->user()->household_id]);
        $this->syncRelated($recipe, $data);
        return response()->json($recipe->load('ingredients', 'ratings'), 201);
    }

    public function update(Request $request, Recipe $recipe): JsonResponse
    {
        $this->authorize($request, $recipe);
        $data = $this->validateData($request);
        $recipe->update($this->scalarData($data));
        $this->syncRelated($recipe, $data);
        return response()->json($recipe->load('ingredients', 'ratings'));
    }

    public function destroy(Request $request, Recipe $recipe): JsonResponse
    {
        $this->authorize($request, $recipe);
        $recipe->delete();
        return response()->json(['ok' => true]);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'servings' => ['integer', 'min:1', 'max:50'],
            'prep_minutes' => ['nullable', 'integer', 'min:0'],
            'source_url' => ['nullable', 'url'],
            'instructions' => ['nullable', 'string'],
            'makes_leftovers' => ['boolean'],
            'default_leftover_servings' => ['integer', 'min:0'],
            'tags' => ['nullable', 'array'],
            'ingredients' => ['nullable', 'array'],
            'ingredients.*.name' => ['required_with:ingredients.*', 'string', 'max:100'],
            'ingredients.*.quantity' => ['nullable', 'string', 'max:20'],
            'ingredients.*.unit' => ['nullable', 'string', 'max:20'],
            'ingredients.*.category' => ['nullable', 'string', 'max:30'],
            'ratings' => ['nullable', 'array'],
            'ratings.*.family_member_id' => ['required_with:ratings.*', 'integer'],
            'ratings.*.rating' => ['required_with:ratings.*', 'in:love,ok,dislike'],
        ]);
    }

    private function scalarData(array $data): array
    {
        return collect($data)->except(['ingredients', 'ratings'])->all();
    }

    private function syncRelated(Recipe $recipe, array $data): void
    {
        if (isset($data['ingredients'])) {
            $recipe->ingredients()->delete();
            foreach ($data['ingredients'] as $i => $ing) {
                RecipeIngredient::create([...$ing, 'recipe_id' => $recipe->id, 'sort_order' => $i]);
            }
        }
        if (isset($data['ratings'])) {
            $recipe->ratings()->delete();
            foreach ($data['ratings'] as $r) {
                $member = \App\Models\FamilyMember::find($r['family_member_id']);
                if ($member && $member->household_id === $recipe->household_id) {
                    RecipeMemberRating::create([...$r, 'recipe_id' => $recipe->id]);
                }
            }
        }
    }

    private function authorize(Request $request, Recipe $recipe): void
    {
        abort_unless($recipe->household_id === $request->user()->household_id, 403);
    }
}
