<?php

namespace App\Models;

use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    protected $fillable = [
        'booking_page_id',
        'guest_name',
        'guest_email',
        'notes',
        'starts_at',
        'ends_at',
        'guest_timezone',
        'status',
        'google_event_id',
        'google_event_link',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    public function bookingPage(): BelongsTo
    {
        return $this->belongsTo(BookingPage::class);
    }
}
