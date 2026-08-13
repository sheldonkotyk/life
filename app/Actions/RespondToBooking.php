<?php

namespace App\Actions;

use App\Contracts\GoogleCalendar;
use App\Mail\BookingHoldReleased;
use App\Models\Booking;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
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

            // Google first: a refused write must leave the request pending
            // rather than claim a meeting that does not exist.
            $this->googleCalendar->confirmEvent(
                $booking->googleCalendarConnection,
                $booking->google_calendar_id,
                $booking->google_event_id,
                $booking,
            );

            $booking->update([
                'status' => Booking::STATUS_CONFIRMED,
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

        // The held event leaves the owner's calendar; the guest was never
        // invited, so nobody is told about a meeting that never happened.
        if ($booking->googleCalendarConnection && filled($booking->google_event_id)) {
            $this->googleCalendar->deleteEvent(
                $booking->googleCalendarConnection,
                $booking->google_calendar_id,
                $booking->google_event_id,
            );
        }

        $booking->update([
            'status' => Booking::STATUS_REJECTED,
            'responded_at' => now(),
        ]);

        $booking->refresh();

        if ($organiser = $booking->googleCalendarConnection?->google_email) {
            Mail::to($booking->guest_email)->send(new BookingHoldReleased($booking, $organiser));
        }

        return $booking;
    }
}
