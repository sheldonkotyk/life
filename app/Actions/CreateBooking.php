<?php

namespace App\Actions;

use App\Contracts\GoogleCalendar;
use App\Models\Booking;
use App\Models\BookingPage;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateBooking
{
    public function __construct(
        private AvailabilityService $availability,
        private GoogleCalendar $googleCalendar,
    ) {}

    /**
     * @param  array{guest_name: string, guest_email: string, notes: string|null, guest_timezone: string}  $guest
     */
    public function execute(BookingPage $bookingPage, CarbonImmutable $startsAt, array $guest): Booking
    {
        $lockName = 'booking:'.$bookingPage->id.':'.$startsAt->utc()->timestamp;

        return Cache::lock($lockName, 15)->block(5, function () use ($bookingPage, $startsAt, $guest): Booking {
            $bookingPage = BookingPage::with([
                'availabilityCalendarSelections.connection.oauthToken',
                'bookingCalendarSelections.connection.oauthToken',
            ])->findOrFail($bookingPage->id);
            $availableStarts = collect($this->availability->slots(
                $bookingPage,
                $startsAt->setTimezone($bookingPage->timezone)->toDateString(),
            ))->pluck('start');

            if (! $availableStarts->contains($startsAt->utc()->toIso8601String())) {
                throw ValidationException::withMessages([
                    'selectedStart' => 'That time is no longer available. Please choose another.',
                ]);
            }

            $destination = $bookingPage->bookingCalendarSelections->sole();

            // Recorded on the booking so a later cancellation still finds the
            // event after the page's calendar choices have moved on.
            $booking = $bookingPage->bookings()->create([
                ...$guest,
                'google_calendar_connection_id' => $destination->google_calendar_connection_id,
                'google_calendar_id' => $destination->google_calendar_id,
                'starts_at' => $startsAt->utc(),
                'ends_at' => $startsAt->addMinutes($bookingPage->duration_minutes)->utc(),
                'status' => Booking::STATUS_PENDING,
            ]);

            try {
                $event = $this->googleCalendar->createEvent(
                    $destination,
                    $bookingPage,
                    $booking,
                );

                $booking->update([
                    'status' => Booking::STATUS_CONFIRMED,
                    'google_event_id' => $event['id'],
                    'google_event_link' => $event['html_link'],
                ]);
            } catch (Throwable $exception) {
                $booking->delete();

                throw $exception;
            }

            return $booking->refresh();
        });
    }
}
