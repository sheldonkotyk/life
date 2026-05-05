<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GlobalRecipe extends Model
{
    protected $guarded = [];

    protected $casts = [
        'tags' => 'array',
    ];

    public function ingredients(): HasMany
    {
        return $this->hasMany(GlobalRecipeIngredient::class)->orderBy('sort_order');
    }

    public function scopeWithAllIngredients(Builder $q, array $ingredients): Builder
    {
        foreach ($ingredients as $needle) {
            $needle = trim((string) $needle);
            if ($needle === '') continue;
            $q->whereHas('ingredients', fn($q2) => $q2->where('name', 'like', "%{$needle}%"));
        }
        return $q;
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') return $q;

        $like = '%' . $term . '%';
        return $q->where(function ($q) use ($like) {
            $q->where('name', 'like', $like)
              ->orWhere('category', 'like', $like)
              ->orWhere('area', 'like', $like)
              ->orWhereHas('ingredients', fn($q2) => $q2->where('name', 'like', $like));
        });
    }
}
