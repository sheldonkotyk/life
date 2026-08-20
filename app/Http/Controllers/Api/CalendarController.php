<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DayAgenda;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Google events for a day, week or month, already filed under each day they touch.
 */
class CalendarController extends Controller
{
    private const VIEWS = ['day', 'week', 'month'];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $timezone = $user->getTimezone();

        $view = $request->query('view');
        $view = in_array($view, self::VIEWS, true) ? $view : 'week';
        $anchor = $this->day($request->query('anchor'), $timezone);

        [$from, $to] = $this->range($view, $anchor);

        $agenda = app(DayAgenda::class)->forRange($user, $from, $to, $timezone);
        $days = $this->days($from, $to);

        return response()->json([
            'view' => $view,
            'anchor' => $anchor->toDateString(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'title' => $this->title($view, $anchor, $from, $to),
            'today' => CarbonImmutable::today($timezone)->toDateString(),
            'timezone' => $timezone,
            'days' => array_map(fn (CarbonImmutable $day) => $day->toDateString(), $days),
            'events' => $agenda['events'],
            'events_by_day' => (object) $this->groupByDay($agenda['events'], $days, $from, $to, $timezone),
            'failed' => $agenda['failed'],
            'calendars' => $agenda['calendars'],
        ]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(string $view, CarbonImmutable $anchor): array
    {
        return match ($view) {
            'day' => [$anchor, $anchor],
            'month' => [
                $anchor->startOfMonth()->startOfWeek(CarbonImmutable::SUNDAY),
                $anchor->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY)->startOfDay(),
            ],
            default => [
                $anchor->startOfWeek(CarbonImmutable::SUNDAY),
                $anchor->endOfWeek(CarbonImmutable::SATURDAY)->startOfDay(),
            ],
        };
    }

    private function title(string $view, CarbonImmutable $anchor, CarbonImmutable $from, CarbonImmutable $to): string
    {
        if ($view === 'day') {
            return $anchor->format('l, F j, Y');
        }

        if ($view === 'month') {
            return $anchor->format('F Y');
        }

        $end = $from->isSameMonth($to) ? $to->format('j, Y') : $to->format('M j, Y');

        return $from->format('M j').' – '.$end;
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function days(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = [];

        for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            $days[] = $day;
        }

        return $days;
    }

    /**
     * A stay away shows on every day it covers, so an event is filed under each.
     *
     * @param  list<array<string, mixed>>  $events
     * @param  list<CarbonImmutable>  $days
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupByDay(array $events, array $days, CarbonImmutable $from, CarbonImmutable $to, string $timezone): array
    {
        $byDay = [];

        foreach ($days as $day) {
            $byDay[$day->toDateString()] = [];
        }

        foreach ($events as $event) {
            $startsAt = CarbonImmutable::parse($event['starts_at'])->setTimezone($timezone);
            $endsAt = CarbonImmutable::parse($event['ends_at'])->setTimezone($timezone);

            // Google ends an all-day event on the following midnight, and a
            // meeting ending at midnight belongs to the day it started.
            $lastDay = $endsAt->equalTo($endsAt->startOfDay()) ? $endsAt->subDay() : $endsAt;

            $day = $startsAt->startOfDay()->max($from);
            $lastDay = $lastDay->startOfDay()->min($to);

            for (; $day->lessThanOrEqualTo($lastDay); $day = $day->addDay()) {
                $byDay[$day->toDateString()][] = $event;
            }
        }

        return $byDay;
    }

    private function day(mixed $value, string $timezone): CarbonImmutable
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return CarbonImmutable::parse($value, $timezone)->startOfDay();
        }

        return CarbonImmutable::today($timezone);
    }
}
