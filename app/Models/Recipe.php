<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    protected $guarded = [];

    protected $casts = [
        'tags' => 'array',
        'makes_leftovers' => 'bool',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class)->orderBy('sort_order');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(RecipeMemberRating::class);
    }

    public function mealPlans(): HasMany
    {
        return $this->hasMany(MealPlan::class);
    }

    public function macroTotals(): array
    {
        $sum = ['calories' => 0.0, 'protein_g' => 0.0, 'carbs_g' => 0.0, 'fat_g' => 0.0];
        foreach ($this->ingredients as $ing) {
            foreach ($sum as $key => $_) {
                $sum[$key] += (float) ($ing->{$key} ?? 0);
            }
        }
        return $sum;
    }

    public function macrosPerServing(): array
    {
        $servings = max(1, (int) $this->servings);
        return array_map(fn($v) => round($v / $servings, 1), $this->macroTotals());
    }
}
