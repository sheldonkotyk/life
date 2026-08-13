<?php

namespace App\Models;

use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'booking_page_id',
        'google_calendar_connection_id',
        'guest_name',
        'guest_email',
        'notes',
        'starts_at',
        'ends_at',
        'guest_timezone',
        'status',
        'google_event_id',
        'google_calendar_id',
        'google_event_link',
        'cancelled_at',
        'rescheduled_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'rescheduled_at' => 'immutable_datetime',
        ];
    }

    public function bookingPage(): BelongsTo
    {
        return $this->belongsTo(BookingPage::class);
    }

    public function googleCalendarConnection(): BelongsTo
    {
        return $this->belongsTo(GoogleCalendarConnection::class);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * A guest keeps no account, so their links are signed and travel with the
     * calendar invitation.
     */
    public function cancelUrl(): string
    {
        return $this->signedGuestUrl('booking.cancel');
    }

    public function rescheduleUrl(): string
    {
        return $this->signedGuestUrl('booking.reschedule');
    }

    private function signedGuestUrl(string $route): string
    {
        return URL::signedRoute($route, [
            'bookingPage' => $this->bookingPage->slug,
            'booking' => $this->id,
        ]);
    }
}
