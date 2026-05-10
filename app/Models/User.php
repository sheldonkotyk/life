<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birthday' => 'date',
            'avatar_config' => 'array',
        ];
    }

    public function hasBuiltAvatar(): bool
    {
        return ! empty($this->avatar_config);
    }

    public function getTimezone(): string
    {
        return $this->timezone ?: 'UTC';
    }

    public function getAvatarAttribute(?string $value): ?string
    {
        if ($this->hasBuiltAvatar()) {
            return $this->builtAvatarDataUri();
        }

        if ($value) {
            return str_starts_with($value, 'http') ? $value : Storage::disk('public')->url($value);
        }

        if (! $this->email) {
            return null;
        }

        $hash = md5(strtolower(trim($this->email)));

        return "https://www.gravatar.com/avatar/{$hash}?s=200&d=mp";
    }

    public function builtAvatarDataUri(): ?string
    {
        if (! $this->hasBuiltAvatar()) {
            return null;
        }

        $svg = view('components.avatar-svg', [
            'config' => $this->avatar_config,
            'background' => '#ffffff',
        ])->render();

        return 'data:image/svg+xml;base64,'.base64_encode(trim($svg));
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function households(): BelongsToMany
    {
        return $this->belongsToMany(Household::class)->withPivot('role')->withTimestamps();
    }

    public function joinHousehold(Household $household, ?string $role = null): void
    {
        if ($role === null && $household->admins()->doesntExist()) {
            $role = 'admin';
        }

        $this->households()->syncWithoutDetaching([
            $household->id => ['role' => $role],
        ]);
        $this->forceFill(['household_id' => $household->id])->save();
    }

    public function familyMember(): HasOne
    {
        return $this->hasOne(FamilyMember::class);
    }

    public function isAdminOf(Household $household): bool
    {
        $membership = $this->households()->where('households.id', $household->id)->first();

        return $membership?->pivot->role === 'admin';
    }

    public function canManageHousehold(Household $household): bool
    {
        if ($this->isAdminOf($household)) {
            return true;
        }

        return $household->users()->wherePivot('role', 'admin')->doesntExist()
            && $household->users()->where('users.id', $this->id)->exists();
    }

    protected static function booted(): void
    {
        static::saved(function (User $user) {
            if ($user->wasChanged('name')) {
                $user->familyMember()->update(['name' => $user->name]);
            }
        });
    }
}
