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
     * @return list<array{start: string, end: string, label: string}>
     */
    public function slots(BookingPage $bookingPage, string $date): array
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

        $localBookings = $bookingPage->bookings()
            ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED])
            ->where('starts_at', '<', $windowEndsAt->utc())
            ->where('ends_at', '>', $windowStartsAt->utc())
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
