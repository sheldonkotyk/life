<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MealPlan extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'save_leftovers' => 'bool',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function leftoverSources(): BelongsToMany
    {
        return $this->belongsToMany(
            MealPlan::class,
            'meal_plan_leftover_uses',
            'meal_plan_id',
            'source_meal_plan_id',
        )->withTimestamps();
    }

    public function leftoverConsumers(): BelongsToMany
    {
        return $this->belongsToMany(
            MealPlan::class,
            'meal_plan_leftover_uses',
            'source_meal_plan_id',
            'meal_plan_id',
        )->withTimestamps();
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(FamilyMember::class, 'meal_attendances')->withPivot('status')->withTimestamps();
    }

    public function confirmedAttendees()
    {
        return $this->attendees->where('pivot.status', '!=', 'not_eating');
    }

    public function skippedIngredients(): BelongsToMany
    {
        return $this->belongsToMany(RecipeIngredient::class, 'meal_plan_skipped_ingredients')->withTimestamps();
    }

    public function effectiveRecipe(): ?Recipe
    {
        return $this->recipe ?? $this->leftoverSources->first()?->recipe;
    }

    public function macrosPerServing(): array
    {
        $empty = ['calories' => 0.0, 'protein_g' => 0.0, 'carbs_g' => 0.0, 'fat_g' => 0.0];

        if ($this->recipe_id) {
            return $this->macrosForRecipe($this->recipe, $this->skippedIngredients->pluck('id')->all());
        }

        $sources = $this->leftoverSources;
        if ($sources->isEmpty()) {
            return $empty;
        }

        $totals = $empty;
        foreach ($sources as $source) {
            $sourceMacros = $this->macrosForRecipe($source->recipe, []);
            foreach ($totals as $k => $_) {
                $totals[$k] += $sourceMacros[$k];
            }
        }

        return array_map(fn ($v) => round($v, 1), $totals);
    }

    private function macrosForRecipe(?Recipe $recipe, array $skippedIds): array
    {
        $sum = ['calories' => 0.0, 'protein_g' => 0.0, 'carbs_g' => 0.0, 'fat_g' => 0.0];
        if (! $recipe) {
            return $sum;
        }
        foreach ($recipe->ingredients as $ing) {
            if (in_array($ing->id, $skippedIds, true)) {
                continue;
            }
            foreach ($sum as $k => $_) {
                $sum[$k] += (float) ($ing->{$k} ?? 0);
            }
        }
        $servings = max(1, (int) $recipe->servings);

        return array_map(fn ($v) => round($v / $servings, 1), $sum);
    }

    public function effectiveStartTime(): ?string
    {
        return $this->resolveTime('start_time', '_start_time');
    }

    public function effectiveEndTime(): ?string
    {
        return $this->resolveTime('end_time', '_end_time');
    }

    private function resolveTime(string $own, string $suffix): ?string
    {
        $value = $this->{$own} ?? optional($this->household)->{$this->slot.$suffix};

        return $value ? substr($value, 0, 5) : null;
    }

    public function displayName(): string
    {
        if ($this->custom_name) {
            return $this->custom_name;
        }
        $sources = $this->leftoverSources;
        if ($sources->isNotEmpty()) {
            $names = $sources->map(fn ($s) => $s->recipe?->name ?? $s->custom_name ?? 'meal')->all();

            return 'Leftovers — '.implode(' + ', $names);
        }

        return $this->recipe?->name ?? '(unplanned)';
    }
}
