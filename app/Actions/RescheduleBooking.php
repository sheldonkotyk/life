<?php

namespace App\Actions;

use App\Contracts\GoogleCalendar;
use App\Models\Booking;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
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

        if ($booking->isAwaitingApproval()) {
            throw ValidationException::withMessages([
                'selectedStart' => 'This request has not been accepted yet, so it cannot be moved. Cancel it and book another time instead.',
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

            return $booking->refresh();
        });
    }
}
