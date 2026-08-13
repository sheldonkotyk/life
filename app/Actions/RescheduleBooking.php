<?php

namespace App\Actions;

use App\Contracts\GoogleCalendar;
use App\Mail\BookingHoldPlaced;
use App\Models\Booking;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RescheduleBooking
{
    public function __construct(
        private AvailabilityService $availability,
        private GoogleCalendar $googleCalendar,
    ) {}

    public function execute(Booking $booking, CarbonImmutable $startsAt): Booking
    {
        if ($booking->isCancelled()) {
            throw ValidationException::withMessages([
                'selectedStart' => 'This meeting was cancelled and can no longer be moved.',
            ]);
        }

        $bookingPage = $booking->bookingPage;
        $lockName = 'booking:'.$bookingPage->id.':'.$startsAt->utc()->timestamp;

        return Cache::lock($lockName, 15)->block(5, function () use ($booking, $bookingPage, $startsAt): Booking {
            $availableStarts = collect($this->availability->slots(
                $bookingPage,
                $startsAt->setTimezone($bookingPage->timezone)->toDateString(),
                $booking,
            ))->pluck('start');

            if (! $availableStarts->contains($startsAt->utc()->toIso8601String())) {
                throw ValidationException::withMessages([
                    'selectedStart' => 'That time is no longer available. Please choose another.',
                ]);
            }

            $connection = $booking->googleCalendarConnection;

            if (! $connection || blank($booking->google_event_id) || blank($booking->google_calendar_id)) {
                throw new RuntimeException('This booking has no Google event to move.');
            }

            $booking->fill([
                'starts_at' => $startsAt->utc(),
                'ends_at' => $startsAt->addMinutes($bookingPage->duration_minutes)->utc(),
            ]);

            // Google moves first: a refused patch must leave the row on the time
            // the calendar still holds.
            $this->googleCalendar->updateEventTime(
                $connection,
                $booking->google_calendar_id,
                $booking->google_event_id,
                $booking,
                $bookingPage->timezone,
            );

            $booking->fill(['rescheduled_at' => now()])->save();
            $booking->refresh();

            // A held request has no invitation yet, so the guest's calendar is
            // moved by re-sending the hold rather than by Google.
            if ($booking->isAwaitingApproval()) {
                Mail::to($booking->guest_email)->send(new BookingHoldPlaced(
                    $booking,
                    $connection->google_email,
                    moved: true,
                ));
            }

            return $booking;
        });
    }
}
