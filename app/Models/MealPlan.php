<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function leftoverOf(): BelongsTo
    {
        return $this->belongsTo(MealPlan::class, 'leftover_of_id');
    }

    public function leftoverMeals(): HasMany
    {
        return $this->hasMany(MealPlan::class, 'leftover_of_id');
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(FamilyMember::class, 'meal_attendances')->withTimestamps();
    }

    public function skippedIngredients(): BelongsToMany
    {
        return $this->belongsToMany(RecipeIngredient::class, 'meal_plan_skipped_ingredients')->withTimestamps();
    }

    public function effectiveRecipe(): ?Recipe
    {
        return $this->recipe ?? $this->leftoverOf?->recipe;
    }

    public function macrosPerServing(): array
    {
        $recipe = $this->effectiveRecipe();
        if (! $recipe) {
            return ['calories' => 0.0, 'protein_g' => 0.0, 'carbs_g' => 0.0, 'fat_g' => 0.0];
        }
        $skipped = $this->skippedIngredients->pluck('id')->all();
        $sum = ['calories' => 0.0, 'protein_g' => 0.0, 'carbs_g' => 0.0, 'fat_g' => 0.0];
        foreach ($recipe->ingredients as $ing) {
            if (in_array($ing->id, $skipped, true)) continue;
            foreach ($sum as $k => $_) $sum[$k] += (float) ($ing->{$k} ?? 0);
        }
        $servings = max(1, (int) $recipe->servings);
        return array_map(fn($v) => round($v / $servings, 1), $sum);
    }

    public function displayName(): string
    {
        if ($this->custom_name) {
            return $this->custom_name;
        }
        if ($this->leftoverOf) {
            $base = $this->leftoverOf->recipe?->name ?? $this->leftoverOf->custom_name ?? 'meal';
            return "Leftovers — {$base}";
        }
        return $this->recipe?->name ?? '(unplanned)';
    }
}
