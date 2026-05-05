<?php

namespace App\Livewire;

use App\Models\FamilyMember;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeMemberRating;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Recipes extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $description = '';
    public int $servings = 5;
    public ?int $prepMinutes = null;
    public string $sourceUrl = '';
    public string $instructions = '';
    public bool $makesLeftovers = false;
    public int $defaultLeftoverServings = 0;

    public array $ingredients = [];
    public array $ratings = [];

    public string $search = '';

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'servings' => ['required', 'integer', 'min:1', 'max:50'],
            'prepMinutes' => ['nullable', 'integer', 'min:0'],
            'sourceUrl' => ['nullable', 'url', 'max:500'],
            'instructions' => ['nullable', 'string'],
            'makesLeftovers' => ['boolean'],
            'defaultLeftoverServings' => ['integer', 'min:0'],
            'ingredients.*.name' => ['nullable', 'string', 'max:100'],
            'ingredients.*.quantity' => ['nullable', 'string', 'max:20'],
            'ingredients.*.unit' => ['nullable', 'string', 'max:20'],
            'ingredients.*.category' => ['nullable', 'string', 'max:30'],
            'ingredients.*.calories' => ['nullable', 'numeric', 'min:0'],
            'ingredients.*.protein_g' => ['nullable', 'numeric', 'min:0'],
            'ingredients.*.carbs_g' => ['nullable', 'numeric', 'min:0'],
            'ingredients.*.fat_g' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function mount(): void
    {
        $this->resetForm();
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $r = $this->householdRecipes()->with('ingredients', 'ratings')->findOrFail($id);
        $this->editingId = $r->id;
        $this->name = $r->name;
        $this->description = $r->description ?? '';
        $this->servings = $r->servings;
        $this->prepMinutes = $r->prep_minutes;
        $this->sourceUrl = $r->source_url ?? '';
        $this->instructions = $r->instructions ?? '';
        $this->makesLeftovers = $r->makes_leftovers;
        $this->defaultLeftoverServings = $r->default_leftover_servings;
        $this->ingredients = $r->ingredients->map(fn($i) => [
            'name' => $i->name, 'quantity' => $i->quantity ?? '', 'unit' => $i->unit ?? '', 'category' => $i->category ?? '',
            'calories' => $i->calories ?? '', 'protein_g' => $i->protein_g ?? '', 'carbs_g' => $i->carbs_g ?? '', 'fat_g' => $i->fat_g ?? '',
        ])->toArray();
        if (empty($this->ingredients)) $this->addIngredientRow();
        $this->ratings = $r->ratings->pluck('rating', 'family_member_id')->toArray();
        $this->showForm = true;
    }

    public function addIngredientRow(): void
    {
        $this->ingredients[] = ['name' => '', 'quantity' => '', 'unit' => '', 'category' => '', 'calories' => '', 'protein_g' => '', 'carbs_g' => '', 'fat_g' => ''];
    }

    public function removeIngredientRow(int $i): void
    {
        unset($this->ingredients[$i]);
        $this->ingredients = array_values($this->ingredients);
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'household_id' => auth()->user()->household_id,
            'name' => $this->name,
            'description' => $this->description ?: null,
            'servings' => $this->servings,
            'prep_minutes' => $this->prepMinutes,
            'source_url' => $this->sourceUrl ?: null,
            'instructions' => $this->instructions ?: null,
            'makes_leftovers' => $this->makesLeftovers,
            'default_leftover_servings' => $this->defaultLeftoverServings,
        ];

        if ($this->editingId) {
            $recipe = $this->householdRecipes()->findOrFail($this->editingId);
            $recipe->update($data);
        } else {
            $recipe = Recipe::create($data);
        }

        $recipe->ingredients()->delete();
        foreach ($this->ingredients as $i => $ing) {
            if (! trim($ing['name'] ?? '')) continue;
            RecipeIngredient::create([
                'recipe_id' => $recipe->id,
                'name' => $ing['name'],
                'quantity' => $ing['quantity'] ?: null,
                'unit' => $ing['unit'] ?: null,
                'category' => $ing['category'] ?: null,
                'calories' => ($ing['calories'] ?? '') === '' ? null : $ing['calories'],
                'protein_g' => ($ing['protein_g'] ?? '') === '' ? null : $ing['protein_g'],
                'carbs_g' => ($ing['carbs_g'] ?? '') === '' ? null : $ing['carbs_g'],
                'fat_g' => ($ing['fat_g'] ?? '') === '' ? null : $ing['fat_g'],
                'sort_order' => $i,
            ]);
        }

        $recipe->ratings()->delete();
        foreach ($this->ratings as $memberId => $rating) {
            if (! in_array($rating, ['love', 'ok', 'dislike'])) continue;
            RecipeMemberRating::create([
                'recipe_id' => $recipe->id,
                'family_member_id' => $memberId,
                'rating' => $rating,
            ]);
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        $this->householdRecipes()->where('id', $id)->delete();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'description', 'prepMinutes', 'sourceUrl', 'instructions']);
        $this->servings = 5;
        $this->makesLeftovers = false;
        $this->defaultLeftoverServings = 0;
        $this->ingredients = [['name' => '', 'quantity' => '', 'unit' => '', 'category' => '', 'calories' => '', 'protein_g' => '', 'carbs_g' => '', 'fat_g' => '']];
        $this->ratings = [];
    }

    private function householdRecipes()
    {
        return Recipe::where('household_id', auth()->user()->household_id);
    }

    public function render()
    {
        $recipes = $this->householdRecipes()
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->withCount('ingredients')
            ->with('ratings.familyMember', 'ingredients')
            ->orderBy('name')
            ->get();

        $members = FamilyMember::where('household_id', auth()->user()->household_id)->orderBy('name')->get();

        return view('livewire.recipes', compact('recipes', 'members'));
    }
}
