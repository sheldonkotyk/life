<?php

namespace App\Actions;

use App\Contracts\GoogleCalendar;
use App\Models\Booking;

class CancelBooking
{
    public function __construct(private GoogleCalendar $googleCalendar) {}

    /**
     * Cancelling twice is a no-op rather than an error: a guest may follow the
     * link in their invitation after the host has already called it off.
     */
    public function execute(Booking $booking): Booking
    {
        if ($booking->isCancelled()) {
            return $booking;
        }

        $connection = $booking->googleCalendarConnection;

        if ($connection && filled($booking->google_event_id) && filled($booking->google_calendar_id)) {
            $this->googleCalendar->deleteEvent(
                $connection,
                $booking->google_calendar_id,
                $booking->google_event_id,
            );
        }

        $booking->update([
            'status' => Booking::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        return $booking->refresh();
    }
}
