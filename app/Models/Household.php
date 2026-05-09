<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Household extends Model
{
    protected $guarded = [];

    protected $casts = [
        'dismissed_meal_names' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Household $h) {
            $h->invite_code ??= strtoupper(Str::random(8));
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function admins(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'admin');
    }

    public function members(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    public function mealPlans(): HasMany
    {
        return $this->hasMany(MealPlan::class);
    }

    public function todoLists(): HasMany
    {
        return $this->hasMany(TodoList::class)->orderBy('position')->orderBy('id');
    }
}
