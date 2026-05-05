<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FamilyMember extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_child' => 'bool',
        'birthday' => 'date',
        'target_calories' => 'float',
        'target_protein_g' => 'float',
        'target_carbs_g' => 'float',
        'target_fat_g' => 'float',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(FoodPreference::class);
    }

    public function likes(): HasMany
    {
        return $this->preferences()->where('type', 'like');
    }

    public function dislikes(): HasMany
    {
        return $this->preferences()->where('type', 'dislike');
    }

    public function allergies(): HasMany
    {
        return $this->preferences()->where('type', 'allergy');
    }

    public function recipeRatings(): HasMany
    {
        return $this->hasMany(RecipeMemberRating::class);
    }

    public function meals(): BelongsToMany
    {
        return $this->belongsToMany(MealPlan::class, 'meal_attendances')->withTimestamps();
    }
}
