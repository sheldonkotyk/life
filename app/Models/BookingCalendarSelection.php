<?php

namespace App\Models;

use Database\Factories\BookingCalendarSelectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingCalendarSelection extends Model
{
    /** @use HasFactory<BookingCalendarSelectionFactory> */
    use HasFactory;

    protected $fillable = [
        'booking_page_id',
        'google_calendar_connection_id',
        'google_calendar_id',
        'google_calendar_name',
        'checks_conflicts',
        'receives_bookings',
    ];

    protected $attributes = [
        'checks_conflicts' => true,
        'receives_bookings' => false,
    ];

    protected function casts(): array
    {
        return [
            'checks_conflicts' => 'boolean',
            'receives_bookings' => 'boolean',
        ];
    }

    public function bookingPage(): BelongsTo
    {
        return $this->belongsTo(BookingPage::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(GoogleCalendarConnection::class, 'google_calendar_connection_id');
    }
}
