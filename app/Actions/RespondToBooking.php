<?php

namespace App\Actions;

use App\Contracts\GoogleCalendar;
use App\Models\Booking;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * The owner's answer to a request that has been holding a slot.
 */
class RespondToBooking
{
    public function __construct(
        private AvailabilityService $availability,
        private GoogleCalendar $googleCalendar,
    ) {}

    public function accept(Booking $booking): Booking
    {
        if (! $booking->isAwaitingApproval()) {
            return $booking;
        }

        $bookingPage = $booking->bookingPage;
        $startsAt = CarbonImmutable::parse($booking->starts_at);
        $lockName = 'booking:'.$bookingPage->id.':'.$startsAt->utc()->timestamp;

        return Cache::lock($lockName, 15)->block(5, function () use ($booking, $bookingPage, $startsAt): Booking {
            // Time may have been taken while the request waited, by a calendar
            // event or by another booking that was accepted first.
            $available = collect($this->availability->slots(
                $bookingPage,
                $startsAt->setTimezone($bookingPage->timezone)->toDateString(),
                $booking,
            ))->pluck('start');

            if (! $available->contains($startsAt->utc()->toIso8601String())) {
                throw ValidationException::withMessages([
                    'bookings' => 'That time is no longer free. Decline the request and suggest another.',
                ]);
            }

            $destination = $bookingPage->bookingCalendarSelections()->sole();

            // Google first: a refused write must leave the request pending
            // rather than claim a meeting that does not exist.
            $event = $this->googleCalendar->createEvent($destination, $bookingPage, $booking);

            $booking->update([
                'status' => Booking::STATUS_CONFIRMED,
                'google_event_id' => $event['id'],
                'google_event_link' => $event['html_link'],
                'google_calendar_id' => $destination->google_calendar_id,
                'google_calendar_connection_id' => $destination->google_calendar_connection_id,
                'responded_at' => now(),
            ]);

            return $booking->refresh();
        });
    }

    public function decline(Booking $booking): Booking
    {
        if (! $booking->isAwaitingApproval()) {
            return $booking;
        }

        $booking->update([
            'status' => Booking::STATUS_REJECTED,
            'responded_at' => now(),
        ]);

        return $booking->refresh();
    }
}
