<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlobalRecipeIngredient extends Model
{
    protected $guarded = [];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(GlobalRecipe::class, 'global_recipe_id');
    }
}
