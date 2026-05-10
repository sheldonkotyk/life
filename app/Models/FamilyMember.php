<?php

namespace App\Models;

use App\Support\Avatar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FamilyMember extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_child' => 'bool',
        'is_guest' => 'bool',
        'birthday' => 'date',
        'default_attendance' => 'array',
        'avatar_config' => 'array',
        'target_calories' => 'float',
        'target_protein_g' => 'float',
        'target_carbs_g' => 'float',
        'target_fat_g' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (FamilyMember $member) {
            if ($member->is_guest && empty($member->avatar_config)) {
                $member->avatar_config = Avatar::randomConfig();
            }
        });

        static::updating(function (FamilyMember $member) {
            if ($member->is_guest && empty($member->avatar_config)) {
                $member->avatar_config = Avatar::randomConfig();
            }
        });
    }

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
        return $this->belongsToMany(MealPlan::class, 'meal_attendances')->withPivot('status')->withTimestamps();
    }

    public function unavailabilities(): HasMany
    {
        return $this->hasMany(FamilyMemberUnavailability::class);
    }

    public function outgoingConnections(): HasMany
    {
        return $this->hasMany(FamilyConnection::class, 'from_member_id');
    }

    public function incomingConnections(): HasMany
    {
        return $this->hasMany(FamilyConnection::class, 'to_member_id');
    }

    public function scopeVisible($query)
    {
        return $query->where(fn ($q) => $q->where('is_guest', false)->orWhereHas('meals'));
    }

    public function attendsByDefault(string $day, string $slot): bool
    {
        return (bool) ($this->default_attendance[$day][$slot] ?? ! $this->is_guest);
    }

    public function setDefaultAttendance(string $day, string $slot, bool $value): void
    {
        $current = $this->default_attendance ?? [];
        $current[$day][$slot] = $value;
        $this->default_attendance = $current;
        $this->save();
    }
}
