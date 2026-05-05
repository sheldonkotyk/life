<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeIngredient extends Model
{
    protected $guarded = [];

    protected $casts = [
        'calories' => 'float',
        'protein_g' => 'float',
        'carbs_g' => 'float',
        'fat_g' => 'float',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
