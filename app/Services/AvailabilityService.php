<?php

namespace App\Services;

use App\Contracts\GoogleCalendar;
use App\Models\Booking;
use App\Models\BookingPage;
use Carbon\CarbonImmutable;

class AvailabilityService
{
    public const MAX_DAYS_AHEAD = 60;

    public function __construct(private GoogleCalendar $googleCalendar) {}

    /**
     * @param  Booking|null  $ignoring  A booking being rescheduled, whose own time should not block it.
     * @return list<array{start: string, end: string, label: string}>
     */
    public function slots(BookingPage $bookingPage, string $date, ?Booking $ignoring = null): array
    {
        $bookingPage->loadMissing([
            'availabilityCalendarSelections.connection.oauthToken',
            'bookingCalendarSelections.connection.oauthToken',
        ]);

        if (! $bookingPage->isReady()) {
            return [];
        }

        $day = CarbonImmutable::parse($date, $bookingPage->timezone)->startOfDay();
        $today = CarbonImmutable::now($bookingPage->timezone)->startOfDay();

        if ($day->toDateString() !== $date
            || $day->lt($today)
            || $day->gt($today->addDays(self::MAX_DAYS_AHEAD))
            || ! in_array($day->dayOfWeekIso, $bookingPage->available_days ?? [], true)
        ) {
            return [];
        }

        $windowStartsAt = CarbonImmutable::parse(
            $date.' '.$bookingPage->availability_starts_at,
            $bookingPage->timezone,
        );
        $windowEndsAt = CarbonImmutable::parse(
            $date.' '.$bookingPage->availability_ends_at,
            $bookingPage->timezone,
        );

        $busyPeriods = [];
        foreach ($bookingPage->availabilityCalendarSelections->groupBy('google_calendar_connection_id') as $selections) {
            $connection = $selections->first()->connection;
            $busyPeriods = [
                ...$busyPeriods,
                ...$this->googleCalendar->busyPeriods(
                    $connection,
                    $selections->pluck('google_calendar_id')->all(),
                    $windowStartsAt,
                    $windowEndsAt,
                    $bookingPage->timezone,
                ),
            ];
        }

        if ($ignoring) {
            $busyPeriods = $this->withoutOwnEvent($busyPeriods, $ignoring);
        }

        $localBookings = $bookingPage->bookings()
            ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED])
            ->where('starts_at', '<', $windowEndsAt->utc())
            ->where('ends_at', '>', $windowStartsAt->utc())
            ->when($ignoring, fn ($query) => $query->whereKeyNot($ignoring->getKey()))
            ->get(['starts_at', 'ends_at']);

        foreach ($localBookings as $booking) {
            $busyPeriods[] = [
                'start' => CarbonImmutable::parse($booking->starts_at),
                'end' => CarbonImmutable::parse($booking->ends_at),
            ];
        }

        $minimumStart = CarbonImmutable::now('UTC')->addHours($bookingPage->minimum_notice_hours);
        $duration = $bookingPage->duration_minutes;
        $step = $duration + $bookingPage->buffer_minutes;
        $slots = [];

        for ($candidate = $windowStartsAt; $candidate->addMinutes($duration)->lte($windowEndsAt); $candidate = $candidate->addMinutes($step)) {
            $candidateEnd = $candidate->addMinutes($duration);

            if ($candidate->utc()->lt($minimumStart) || $this->overlaps($candidate, $candidateEnd, $busyPeriods, $bookingPage->buffer_minutes)) {
                continue;
            }

            $slots[] = [
                'start' => $candidate->utc()->toIso8601String(),
                'end' => $candidateEnd->utc()->toIso8601String(),
                'label' => $candidate->format('g:i A'),
            ];
        }

        return $slots;
    }

    /**
     * Drop the busy period Google reports for the booking's own event, so a
     * guest rescheduling is not blocked by the meeting they are moving.
     *
     * Freebusy returns no event ids, so only an exact match is dropped: a
     * period merged with a neighbouring event stays busy rather than risk
     * freeing time that is genuinely taken.
     *
     * @param  list<array{start: CarbonImmutable, end: CarbonImmutable}>  $busyPeriods
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function withoutOwnEvent(array $busyPeriods, Booking $booking): array
    {
        // A request still waiting for approval owns no event, so anything busy
        // at that time belongs to someone else.
        if (blank($booking->google_event_id)) {
            return $busyPeriods;
        }

        return array_values(array_filter(
            $busyPeriods,
            fn (array $period): bool => ! $period['start']->eq($booking->starts_at)
                || ! $period['end']->eq($booking->ends_at),
        ));
    }

    /**
     * @param  list<array{start: CarbonImmutable, end: CarbonImmutable}>  $busyPeriods
     */
    private function overlaps(
        CarbonImmutable $candidateStartsAt,
        CarbonImmutable $candidateEndsAt,
        array $busyPeriods,
        int $bufferMinutes,
    ): bool {
        foreach ($busyPeriods as $period) {
            if ($candidateStartsAt->lt($period['end']->addMinutes($bufferMinutes))
                && $candidateEndsAt->gt($period['start']->subMinutes($bufferMinutes))
            ) {
                return true;
            }
        }

        return false;
    }
}
