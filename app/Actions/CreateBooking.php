<?php

namespace App\Actions;

use App\Contracts\GoogleCalendar;
use App\Mail\BookingHoldPlaced;
use App\Mail\BookingReceived;
use App\Models\Booking;
use App\Models\BookingPage;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateBooking
{
    public function __construct(
        private AvailabilityService $availability,
        private GoogleCalendar $googleCalendar,
    ) {}

    /**
     * @param  array{guest_name: string, guest_email: string, guest_title: string|null, notes: string|null, guest_timezone: string}  $guest
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
                // An approval page pencils the request into the owner's agenda
                // as tentative; only accepting invites the guest.
                $event = $this->googleCalendar->createEvent(
                    $destination,
                    $bookingPage,
                    $booking,
                    $bookingPage->requires_approval,
                );

                $booking->update([
                    'status' => $bookingPage->requires_approval
                        ? Booking::STATUS_PENDING
                        : Booking::STATUS_CONFIRMED,
                    'google_event_id' => $event['id'],
                    'google_ical_uid' => $event['ical_uid'] ?? null,
                    'google_event_link' => $event['html_link'],
                ]);
            } catch (Throwable $exception) {
                $booking->delete();

                throw $exception;
            }

            $booking->refresh();

            // The owner's calendar holds the time; this puts the same hold on
            // the guest's, since they were not invited to the tentative event.
            if ($bookingPage->requires_approval) {
                Mail::to($booking->guest_email)->send(new BookingHoldPlaced(
                    $booking,
                    $destination->connection->google_email,
                ));
            }

            // The calendar entry alone is easy to miss, so the owner is told
            // directly, with the same links the entry carries.
            $owner = $bookingPage->user;
            if ($owner->wantsBookingEmails()) {
                Mail::to($owner->email)->send(new BookingReceived($booking));
            }

            return $booking;
        });
    }
}
