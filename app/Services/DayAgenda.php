<?php

namespace App\Services;

use App\Contracts\GoogleCalendar;
use App\Models\BookingCalendarSelection;
use App\Models\GoogleCalendarConnection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * One day of a user's Google calendars, merged across every connected account.
 *
 * The calendars shown are the ones already marked as blocking availability, so
 * the agenda matches what the booking pages treat as real commitments.
 */
class DayAgenda
{
    private const CACHE_SECONDS = 120;

    /**
     * Bumped whenever the cached shape changes, so a deploy cannot serve a
     * payload the new view does not understand.
     */
    private const CACHE_VERSION = 3;

    public function __construct(private GoogleCalendar $googleCalendar) {}

    /**
     * @return array{events: list<array<string, mixed>>, failed: bool, calendars: int}
     */
    public function forUser(User $user, CarbonImmutable $day, string $timezone): array
    {
        $selections = $this->calendars($user);

        if ($selections->isEmpty()) {
            return ['events' => [], 'failed' => false, 'calendars' => 0];
        }

        $key = self::cacheKey($user, $day, $timezone);

        return Cache::remember(
            $key,
            self::CACHE_SECONDS,
            fn (): array => $this->fetch($selections, $day, $timezone),
        );
    }

    public static function forget(User $user, CarbonImmutable $day, string $timezone): void
    {
        Cache::forget(self::cacheKey($user, $day, $timezone));
    }

    private static function cacheKey(User $user, CarbonImmutable $day, string $timezone): string
    {
        return 'agenda:v'.self::CACHE_VERSION.':'.$user->id.':'.$day->toDateString().':'.$timezone;
    }

    /**
     * Distinct calendars this user checks for conflicts, grouped by account.
     *
     * @return Collection<int, Collection<int, BookingCalendarSelection>>
     */
    private function calendars(User $user)
    {
        return BookingCalendarSelection::query()
            ->where('checks_conflicts', true)
            ->whereHas('bookingPage', fn ($query) => $query->where('user_id', $user->id))
            ->with('connection.oauthToken')
            ->get()
            ->filter(fn (BookingCalendarSelection $selection): bool => $selection->connection !== null)
            ->unique(fn (BookingCalendarSelection $selection): string => $selection->google_calendar_connection_id.'|'.$selection->google_calendar_id)
            ->groupBy('google_calendar_connection_id');
    }

    /**
     * @param  Collection<int, Collection<int, BookingCalendarSelection>>  $selections
     * @return array{events: list<array<string, mixed>>, failed: bool, calendars: int}
     */
    private function fetch($selections, CarbonImmutable $day, string $timezone): array
    {
        $events = [];
        $failed = false;
        $calendars = 0;

        foreach ($selections as $accountSelections) {
            /** @var GoogleCalendarConnection $connection */
            $connection = $accountSelections->first()->connection;
            $names = $accountSelections->pluck('google_calendar_name', 'google_calendar_id');
            $calendars += $accountSelections->count();

            try {
                $found = $this->googleCalendar->eventsBetween(
                    $connection,
                    $accountSelections->pluck('google_calendar_id')->values()->all(),
                    $day->startOfDay(),
                    $day->endOfDay(),
                    $timezone,
                );
            } catch (Throwable $exception) {
                // One unreadable account should not empty the whole day.
                report($exception);
                $failed = true;

                continue;
            }

            foreach ($found as $event) {
                $events[] = [
                    ...$event,
                    'starts_at' => $event['starts_at']->toIso8601String(),
                    'ends_at' => $event['ends_at']->toIso8601String(),
                    'account' => $connection->google_email,
                    'calendar_name' => $names[$event['calendar_id']] ?? $event['calendar_id'],
                ];
            }
        }

        usort(
            $events,
            fn (array $a, array $b): int => [$b['all_day'], $a['starts_at']] <=> [$a['all_day'], $b['starts_at']],
        );

        return ['events' => $events, 'failed' => $failed, 'calendars' => $calendars];
    }
}
