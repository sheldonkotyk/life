<?php

namespace App\Models;

use CleaniqueCoders\TokenVault\Concerns\InteractsWithTokenVault;
use Database\Factories\GoogleCalendarConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class GoogleCalendarConnection extends Model
{
    /** @use HasFactory<GoogleCalendarConnectionFactory> */
    use HasFactory, InteractsWithTokenVault;

    protected $fillable = [
        'user_id',
        'google_user_id',
        'google_email',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function oauthToken(): MorphOne
    {
        return $this->morphOne(GoogleCalendarToken::class, 'tokenable')->latestOfMany();
    }

    public function calendarSelections(): HasMany
    {
        return $this->hasMany(BookingCalendarSelection::class);
    }

    public function isHealthy(): bool
    {
        return $this->oauthToken !== null
            && filled($this->oauthToken->meta['last_four']['refresh_token'] ?? null);
    }

    protected static function booted(): void
    {
        static::deleting(function (GoogleCalendarConnection $connection): void {
            $connection->tokens()->delete();
        });
    }
}
