<?php

namespace App\Services;

use App\Models\GlobalRecipe;
use App\Models\GlobalRecipeIngredient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TheMealDbImporter
{
    private string $base;
    private string $version;

    public function __construct()
    {
        $key = config('services.themealdb.key', '1');
        $this->version = config('services.themealdb.version', 'v1');
        $this->base = "https://www.themealdb.com/api/json/{$this->version}/{$key}";
    }

    public function isV2(): bool
    {
        return $this->version === 'v2';
    }

    public function importAll(?callable $onMeal = null): int
    {
        $count = 0;
        foreach (range('a', 'z') as $letter) {
            foreach ($this->mealsByLetter($letter) as $meal) {
                $this->upsertMeal($meal);
                $count++;
                if ($onMeal) $onMeal($meal, $count);
            }
        }
        return $count;
    }

    public function importByLetter(string $letter, ?callable $onMeal = null): int
    {
        $count = 0;
        foreach ($this->mealsByLetter($letter) as $meal) {
            $this->upsertMeal($meal);
            $count++;
            if ($onMeal) $onMeal($meal, $count);
        }
        return $count;
    }

    public function importLatest(?callable $onMeal = null): int
    {
        if (! $this->isV2()) {
            throw new \RuntimeException('latest.php requires v2 API');
        }
        $meals = Http::get("{$this->base}/latest.php")->json()['meals'] ?? [];
        $count = 0;
        foreach ($meals as $meal) {
            $this->upsertMeal($meal);
            $count++;
            if ($onMeal) $onMeal($meal, $count);
        }
        return $count;
    }

    public function mealsByLetter(string $letter): array
    {
        $res = Http::get("{$this->base}/search.php", ['f' => $letter])->json();
        return $res['meals'] ?? [];
    }

    public function lookup(string $id): ?array
    {
        $res = Http::get("{$this->base}/lookup.php", ['i' => $id])->json();
        return $res['meals'][0] ?? null;
    }

    /**
     * Discover meal stubs that contain all of the given ingredients (v2 multi-ingredient filter).
     * Returns array of ['idMeal', 'strMeal', 'strMealThumb'] — call importById() to fetch details.
     */
    public function filterByIngredients(array $ingredients): array
    {
        $clean = array_values(array_filter(array_map(
            fn($i) => str_replace(' ', '_', trim((string) $i)),
            $ingredients
        )));
        if (empty($clean)) return [];

        $res = Http::get("{$this->base}/filter.php", ['i' => implode(',', $clean)])->json();
        return is_array($res['meals'] ?? null) ? $res['meals'] : [];
    }

    public function importById(string $id): ?GlobalRecipe
    {
        $meal = $this->lookup($id);
        return $meal ? $this->upsertMeal($meal) : null;
    }

    public function upsertMeal(array $meal): GlobalRecipe
    {
        return DB::transaction(function () use ($meal) {
            $recipe = GlobalRecipe::updateOrCreate(
                ['source' => 'themealdb', 'external_id' => $meal['idMeal']],
                [
                    'name' => $meal['strMeal'] ?? 'Untitled',
                    'category' => $meal['strCategory'] ?? null,
                    'area' => $meal['strArea'] ?? null,
                    'instructions' => $meal['strInstructions'] ?? null,
                    'image_url' => $meal['strMealThumb'] ?? null,
                    'youtube_url' => $meal['strYoutube'] ?? null,
                    'source_url' => $meal['strSource'] ?? null,
                    'tags' => $this->parseTags($meal['strTags'] ?? null),
                ]
            );

            $recipe->ingredients()->delete();
            $rows = [];
            for ($i = 1; $i <= 20; $i++) {
                $name = trim((string) ($meal["strIngredient{$i}"] ?? ''));
                if ($name === '') continue;
                $rows[] = [
                    'global_recipe_id' => $recipe->id,
                    'name' => $name,
                    'measure' => trim((string) ($meal["strMeasure{$i}"] ?? '')) ?: null,
                    'sort_order' => $i,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if ($rows) GlobalRecipeIngredient::insert($rows);

            return $recipe;
        });
    }

    private function parseTags(?string $tags): ?array
    {
        if (! $tags) return null;
        $list = array_filter(array_map('trim', explode(',', $tags)));
        return $list ? array_values($list) : null;
    }
}
