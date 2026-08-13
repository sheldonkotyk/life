<?php

namespace App\Actions;

use App\Contracts\GoogleCalendar;
use App\Mail\BookingGuestDeclined;
use App\Models\Booking;
use App\Models\BookingCalendarSelection;
use App\Models\CalendarSyncState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Reconciles bookings with what has since happened in Google: a guest turning
 * an invitation down, or the owner moving or deleting the meeting themselves.
 *
 * Only explicit signals are acted on. An API failure leaves everything as it
 * is, because a bad minute at Google must not cancel real meetings.
 */
class SyncCalendarChanges
{
    public function __construct(private GoogleCalendar $googleCalendar) {}

    /**
     * @return array{calendars: int, changed: int, failed: int}
     */
    public function execute(): array
    {
        $calendars = 0;
        $changed = 0;
        $failed = 0;

        foreach ($this->destinations() as $destination) {
            $calendars++;

            try {
                $changed += $this->syncCalendar($destination);
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        return ['calendars' => $calendars, 'changed' => $changed, 'failed' => $failed];
    }

    /**
     * Only calendars that receive bookings can hold events worth reconciling.
     *
     * @return Collection<int, BookingCalendarSelection>
     */
    private function destinations()
    {
        return BookingCalendarSelection::query()
            ->where('receives_bookings', true)
            ->with('connection.oauthToken')
            ->get()
            ->filter(fn (BookingCalendarSelection $selection): bool => $selection->connection !== null)
            ->unique(fn (BookingCalendarSelection $selection): string => $selection->google_calendar_connection_id.'|'.$selection->google_calendar_id);
    }

    /**
     * Reconcile one calendar, for a push notification naming it.
     */
    public function syncOne(CalendarSyncState $state): int
    {
        $destination = $this->destinations()->first(
            fn (BookingCalendarSelection $selection): bool => $selection->google_calendar_connection_id === $state->google_calendar_connection_id
                && $selection->google_calendar_id === $state->google_calendar_id,
        );

        return $destination ? $this->syncCalendar($destination) : 0;
    }

    private function syncCalendar(BookingCalendarSelection $destination): int
    {
        $state = CalendarSyncState::firstOrCreate([
            'google_calendar_connection_id' => $destination->google_calendar_connection_id,
            'google_calendar_id' => $destination->google_calendar_id,
        ]);

        $result = $this->googleCalendar->changedEvents(
            $destination->connection,
            $destination->google_calendar_id,
            $state->sync_token,
        );

        if ($result['expired']) {
            // Google forgot the token; start again from a full window.
            $state->update(['sync_token' => null]);

            $result = $this->googleCalendar->changedEvents(
                $destination->connection,
                $destination->google_calendar_id,
                null,
            );
        }

        $changed = 0;

        foreach ($result['events'] as $event) {
            $changed += $this->applyEvent($destination, $event) ? 1 : 0;
        }

        $state->update([
            'sync_token' => $result['sync_token'] ?: $state->sync_token,
            'synced_at' => now(),
        ]);

        return $changed;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function applyEvent(BookingCalendarSelection $destination, array $event): bool
    {
        $eventId = $event['id'] ?? null;

        if (! is_string($eventId)) {
            return false;
        }

        $booking = Booking::query()
            ->where('google_event_id', $eventId)
            ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED])
            ->first();

        if (! $booking) {
            return false;
        }

        if (($event['status'] ?? null) === 'cancelled') {
            $booking->update([
                'status' => Booking::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            return true;
        }

        if ($this->guestDeclined($booking, $event)) {
            $this->releaseDeclined($booking, $destination);

            return true;
        }

        return $this->applyMovedTimes($booking, $event);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function guestDeclined(Booking $booking, array $event): bool
    {
        foreach ($event['attendees'] ?? [] as $attendee) {
            $email = mb_strtolower((string) ($attendee['email'] ?? ''));

            if ($email === mb_strtolower($booking->guest_email)) {
                return ($attendee['responseStatus'] ?? null) === 'declined';
            }
        }

        return false;
    }

    private function releaseDeclined(Booking $booking, BookingCalendarSelection $destination): void
    {
        try {
            $this->googleCalendar->deleteEvent(
                $destination->connection,
                $destination->google_calendar_id,
                $booking->google_event_id,
            );
        } catch (Throwable $exception) {
            // The booking is still cancelled: the guest has said no, whatever
            // state the calendar entry is in.
            report($exception);
        }

        $booking->update([
            'status' => Booking::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        $owner = $booking->bookingPage->user;

        if ($owner->wantsBookingEmails()) {
            Mail::to($owner->email)->send(new BookingGuestDeclined($booking->refresh()));
        }
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function applyMovedTimes(Booking $booking, array $event): bool
    {
        $start = $event['start']['dateTime'] ?? null;
        $end = $event['end']['dateTime'] ?? null;

        if (! is_string($start) || ! is_string($end)) {
            return false;
        }

        $startsAt = CarbonImmutable::parse($start)->utc();
        $endsAt = CarbonImmutable::parse($end)->utc();

        if ($startsAt->eq($booking->starts_at) && $endsAt->eq($booking->ends_at)) {
            return false;
        }

        $booking->update([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'rescheduled_at' => now(),
        ]);

        return true;
    }
}
