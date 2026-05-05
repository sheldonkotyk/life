<?php

namespace App\Livewire;

use App\Models\GlobalRecipe;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Services\TheMealDbImporter;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class RecipeBrowser extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $area = '';

    #[Url(as: 'i')]
    public array $ingredients = [];

    public string $ingredientInput = '';

    public ?int $previewId = null;

    /** Discovered (not yet imported) meal stubs from TheMealDB. */
    public array $discovered = [];

    public bool $discovering = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategory(): void
    {
        $this->resetPage();
    }

    public function updatingArea(): void
    {
        $this->resetPage();
    }

    public function addIngredient(): void
    {
        $value = trim($this->ingredientInput);
        if ($value === '') return;
        if (! in_array($value, $this->ingredients, true)) {
            $this->ingredients[] = $value;
        }
        $this->ingredientInput = '';
        $this->discovered = [];
        $this->resetPage();
    }

    public function removeIngredient(string $name): void
    {
        $this->ingredients = array_values(array_filter(
            $this->ingredients,
            fn($i) => $i !== $name
        ));
        $this->discovered = [];
        $this->resetPage();
    }

    public function discoverOnline(TheMealDbImporter $importer): void
    {
        if (empty($this->ingredients)) return;

        $this->discovering = true;
        try {
            $stubs = $importer->filterByIngredients($this->ingredients);
            $existing = GlobalRecipe::where('source', 'themealdb')
                ->whereIn('external_id', array_column($stubs, 'idMeal'))
                ->pluck('external_id')
                ->all();

            $this->discovered = array_values(array_filter(
                $stubs,
                fn($s) => ! in_array($s['idMeal'], $existing, true)
            ));
        } finally {
            $this->discovering = false;
        }
    }

    public function importDiscovered(string $externalId, TheMealDbImporter $importer): void
    {
        $importer->importById($externalId);
        $this->discovered = array_values(array_filter(
            $this->discovered,
            fn($s) => $s['idMeal'] !== $externalId
        ));
    }

    public function preview(int $id): void
    {
        $this->previewId = $id;
    }

    public function closePreview(): void
    {
        $this->previewId = null;
    }

    public function importToHousehold(int $id): void
    {
        $user = auth()->user();
        abort_unless($user && $user->household_id, 403);

        $global = GlobalRecipe::with('ingredients')->findOrFail($id);

        DB::transaction(function () use ($global, $user) {
            $recipe = Recipe::create([
                'household_id' => $user->household_id,
                'name' => $global->name,
                'description' => $global->category && $global->area
                    ? "{$global->area} · {$global->category}"
                    : ($global->category ?? $global->area),
                'servings' => 4,
                'source_url' => $global->source_url ?? $global->youtube_url,
                'instructions' => $global->instructions,
                'tags' => $global->tags,
            ]);

            foreach ($global->ingredients as $i => $ing) {
                RecipeIngredient::create([
                    'recipe_id' => $recipe->id,
                    'name' => $ing->name,
                    'quantity' => $ing->measure,
                    'sort_order' => $i,
                ]);
            }
        });

        $this->dispatch('recipe-imported');
        session()->flash('status', "Added “{$global->name}” to your recipes.");
    }

    public function render()
    {
        $recipes = GlobalRecipe::query()
            ->search($this->search)
            ->when($this->category, fn($q) => $q->where('category', $this->category))
            ->when($this->area, fn($q) => $q->where('area', $this->area))
            ->when($this->ingredients, fn($q) => $q->withAllIngredients($this->ingredients))
            ->orderBy('name')
            ->paginate(24);

        $categories = GlobalRecipe::query()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $areas = GlobalRecipe::query()
            ->whereNotNull('area')
            ->distinct()
            ->orderBy('area')
            ->pluck('area');

        $preview = $this->previewId
            ? GlobalRecipe::with('ingredients')->find($this->previewId)
            : null;

        return view('livewire.recipe-browser', compact('recipes', 'categories', 'areas', 'preview'));
    }
}
