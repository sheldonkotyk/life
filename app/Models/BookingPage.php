<?php

namespace App\Models;

use Database\Factories\BookingPageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BookingPage extends Model
{
    /** @use HasFactory<BookingPageFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'slug',
        'is_enabled',
        'title',
        'description',
        'duration_minutes',
        'minimum_notice_hours',
        'buffer_minutes',
        'timezone',
        'availability_starts_at',
        'availability_ends_at',
        'available_days',
    ];

    protected $attributes = [
        'is_enabled' => false,
        'title' => 'Meet with me',
        'duration_minutes' => 30,
        'minimum_notice_hours' => 2,
        'buffer_minutes' => 0,
        'timezone' => 'UTC',
        'availability_starts_at' => '09:00',
        'availability_ends_at' => '17:00',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'available_days' => 'array',
            'duration_minutes' => 'integer',
            'minimum_notice_hours' => 'integer',
            'buffer_minutes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function calendarSelections(): HasMany
    {
        return $this->hasMany(BookingCalendarSelection::class);
    }

    public function availabilityCalendarSelections(): HasMany
    {
        return $this->calendarSelections()->where('checks_conflicts', true);
    }

    public function bookingCalendarSelections(): HasMany
    {
        return $this->calendarSelections()->where('receives_bookings', true);
    }

    public function isReady(): bool
    {
        return $this->is_enabled
            && $this->bookingCalendarSelections()->exists()
            && $this->availabilityCalendarSelections()->exists();
    }

    public static function uniqueSlugFor(User $user): string
    {
        $base = Str::slug($user->name) ?: 'meet';
        $slug = $base;
        $suffix = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
