<?php

namespace App\Livewire;

use App\Services\DayAgenda;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Calendar extends Component
{
    public const VIEWS = ['day', 'week', 'month'];

    #[Url(as: 'view', except: 'week')]
    public string $view = 'week';

    /** The day the visible range is built around, as Y-m-d. */
    public string $anchor = '';

    public function mount(): void
    {
        if (! in_array($this->view, self::VIEWS, true)) {
            $this->view = 'week';
        }

        $this->anchor = $this->day(request()->query('date'))->toDateString();
    }

    public function setView(string $view): void
    {
        if (in_array($view, self::VIEWS, true)) {
            $this->view = $view;
        }
    }

    /**
     * Move one day, week or month, whichever the current view shows.
     */
    public function shift(int $direction): void
    {
        $direction = $direction < 0 ? -1 : 1;
        $anchor = $this->day($this->anchor);

        $moved = match ($this->view) {
            'day' => $anchor->addDays($direction),
            // Anchoring to the first of the month keeps the 31st from
            // skipping a short month.
            'month' => $anchor->startOfMonth()->addMonths($direction),
            default => $anchor->addWeeks($direction),
        };

        $this->anchor = $moved->toDateString();
    }

    public function jumpToToday(): void
    {
        $this->anchor = CarbonImmutable::today(auth()->user()->getTimezone())->toDateString();
    }

    public function openDay(string $date): void
    {
        $this->anchor = $this->day($date)->toDateString();
        $this->view = 'day';
    }

    public function render()
    {
        $user = auth()->user();
        $timezone = $user->getTimezone();
        [$from, $to] = $this->range();

        $agenda = app(DayAgenda::class)->forRange($user, $from, $to, $timezone);

        return view('livewire.calendar', [
            'agenda' => $agenda,
            'days' => $this->days($from, $to),
            'eventsByDay' => $this->groupByDay($agenda['events'], $from, $to, $timezone),
            'today' => CarbonImmutable::today($timezone),
            'anchorDay' => $this->day($this->anchor),
            'title' => $this->title(),
            'timezone' => $timezone,
        ]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(): array
    {
        $anchor = $this->day($this->anchor);

        return match ($this->view) {
            'day' => [$anchor, $anchor],
            // The month grid runs whole weeks, so it spills into its neighbours.
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

    private function title(): string
    {
        $anchor = $this->day($this->anchor);
        [$from, $to] = $this->range();

        if ($this->view === 'day') {
            return $anchor->format('l, F j, Y');
        }

        if ($this->view === 'month') {
            return $anchor->format('F Y');
        }

        $end = $from->isSameMonth($to) ? $to->format('j, Y') : $to->format('M j, Y');

        return $from->format('M j').' – '.$end;
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    private function days(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $days = [];

        for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            $days[] = $day;
        }

        return collect($days);
    }

    /**
     * Events filed under every day they touch, so a stay away shows all week.
     *
     * @param  list<array<string, mixed>>  $events
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupByDay(array $events, CarbonImmutable $from, CarbonImmutable $to, string $timezone): array
    {
        $byDay = [];

        foreach ($this->days($from, $to) as $day) {
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

    private function day(mixed $date): CarbonImmutable
    {
        $timezone = auth()->user()->getTimezone();

        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            return CarbonImmutable::parse($date, $timezone)->startOfDay();
        }

        return CarbonImmutable::today($timezone);
    }
}
