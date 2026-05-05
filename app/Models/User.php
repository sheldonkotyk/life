<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
        ];
    }

    public function getTimezone(): string
    {
        return $this->timezone ?: 'UTC';
    }

    public function getAvatarAttribute(?string $value): ?string
    {
        if ($value) {
            return $value;
        }

        if (! $this->email) {
            return null;
        }

        $hash = md5(strtolower(trim($this->email)));

        return "https://www.gravatar.com/avatar/{$hash}?s=200&d=mp";
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function familyMember(): HasOne
    {
        return $this->hasOne(FamilyMember::class);
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
