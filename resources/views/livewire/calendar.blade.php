<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl">Calendar</flux:heading>

        <flux:button.group>
            @foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $key => $label)
                <flux:button
                    size="sm"
                    :variant="$view === $key ? 'primary' : 'outline'"
                    wire:click="setView('{{ $key }}')"
                >{{ $label }}</flux:button>
            @endforeach
        </flux:button.group>
    </div>

    <flux:card class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <flux:heading size="lg">{{ $title }}</flux:heading>
                @if ($agenda['calendars'] > 0)
                    <flux:text size="sm" variant="subtle">
                        {{ $agenda['calendars'] }} {{ Str::plural('calendar', $agenda['calendars']) }}
                    </flux:text>
                @endif
            </div>

            <div class="flex items-center gap-1">
                <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="shift(-1)" aria-label="Previous" />
                <flux:button size="sm" variant="ghost" wire:click="jumpToToday">Today</flux:button>
                <flux:button size="sm" variant="ghost" icon="chevron-right" wire:click="shift(1)" aria-label="Next" />
            </div>
        </div>

        @if ($agenda['failed'])
            <flux:callout color="amber" icon="exclamation-triangle">
                One of your Google accounts could not be read, so this may be missing events.
            </flux:callout>
        @endif

        @if ($agenda['calendars'] === 0)
            <flux:text variant="subtle">
                No calendars are connected yet.
                <flux:link :href="route('booking.settings')">Connect a Google account</flux:link>
                to see your days here.
            </flux:text>
        @elseif ($view === 'day')
            @php $events = $eventsByDay[$anchorDay->toDateString()] ?? []; @endphp

            @if ($events === [])
                <flux:text variant="subtle">Nothing on your calendars this day.</flux:text>
            @else
                <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($events as $event)
                        @php
                            $startsAt = \Carbon\CarbonImmutable::parse($event['starts_at'])->setTimezone($timezone);
                            $endsAt = \Carbon\CarbonImmutable::parse($event['ends_at'])->setTimezone($timezone);
                            $guests = collect($event['attendees'] ?? [])->reject(fn ($a) => $a['self'] ?? false)->values();
                            $modalName = 'event-'.md5($event['calendar_id'].$event['id']);
                            $kind = match ($event['type'] ?? 'default') {
                                'workingLocation' => 'Working location',
                                'focusTime' => 'Focus time',
                                'outOfOffice' => 'Out of office',
                                default => null,
                            };
                        @endphp
                        <div wire:key="day-{{ md5($event['calendar_id'].$event['id']) }}">
                            <flux:modal.trigger :name="$modalName">
                                <button type="button" class="-mx-2 flex w-full cursor-pointer flex-col gap-1 rounded px-2 py-3 text-left transition hover:bg-zinc-50 sm:flex-row sm:items-baseline sm:gap-4 dark:hover:bg-zinc-800">
                                    <div class="w-36 shrink-0 text-sm font-medium whitespace-nowrap text-zinc-500 sm:w-44">
                                        @if ($event['all_day'])
                                            All day
                                        @else
                                            {{ $startsAt->format('g:i A') }} – {{ $endsAt->format('g:i A') }}
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="truncate font-medium {{ $kind ? 'text-zinc-600 dark:text-zinc-400' : 'text-zinc-900 dark:text-white' }}">{{ $event['title'] }}</span>
                                            @if ($kind)
                                                <flux:badge size="sm" color="zinc">{{ $kind }}</flux:badge>
                                            @endif
                                        </div>
                                        <div class="truncate text-xs text-zinc-500">
                                            {{ $event['calendar_name'] === $event['account'] ? $event['account'] : $event['calendar_name'].' · '.$event['account'] }}
                                            @if ($guests->isNotEmpty())
                                                · {{ $guests->count() }} {{ Str::plural('guest', $guests->count()) }}
                                            @endif
                                        </div>
                                    </div>
                                </button>
                            </flux:modal.trigger>

                            <x-calendar-event :name="$modalName" :event="$event" :timezone="$timezone" />
                        </div>
                    @endforeach
                </div>
            @endif
        @elseif ($view === 'week')
            <div class="grid gap-4 sm:grid-cols-7 sm:gap-2">
                @foreach ($days as $day)
                    @php $events = $eventsByDay[$day->toDateString()] ?? []; @endphp
                    <div class="min-w-0" wire:key="week-{{ $day->toDateString() }}">
                        <button
                            type="button"
                            wire:click="openDay('{{ $day->toDateString() }}')"
                            class="flex w-full cursor-pointer items-baseline gap-2 rounded px-1 py-1 text-left transition hover:bg-zinc-50 sm:block dark:hover:bg-zinc-800"
                        >
                            <span class="text-xs font-medium tracking-wide text-zinc-500 uppercase">{{ $day->format('D') }}</span>
                            <span class="text-lg font-semibold sm:block">
                                <span @class([
                                    'inline-flex size-8 items-center justify-center rounded-full',
                                    'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => $day->isSameDay($today),
                                    'text-zinc-900 dark:text-white' => ! $day->isSameDay($today),
                                ])>{{ $day->format('j') }}</span>
                            </span>
                        </button>

                        <div class="mt-1 space-y-1">
                            @forelse ($events as $event)
                                @php $modalName = 'week-'.md5($day->toDateString().$event['calendar_id'].$event['id']); @endphp
                                <div wire:key="{{ $modalName }}">
                                    <flux:modal.trigger :name="$modalName">
                                        <button
                                            type="button"
                                            @class([
                                                'block w-full cursor-pointer truncate rounded px-1.5 py-1 text-left text-xs transition',
                                                'bg-zinc-200 font-medium text-zinc-800 hover:bg-zinc-300 dark:bg-zinc-700 dark:text-zinc-100 dark:hover:bg-zinc-600' => $event['all_day'],
                                                'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700' => ! $event['all_day'],
                                            ])
                                        >
                                            @unless ($event['all_day'])
                                                <span class="text-zinc-500 dark:text-zinc-400">{{ \Carbon\CarbonImmutable::parse($event['starts_at'])->setTimezone($timezone)->format('g:i A') }}</span>
                                            @endunless
                                            {{ $event['title'] }}
                                        </button>
                                    </flux:modal.trigger>

                                    <x-calendar-event :name="$modalName" :event="$event" :timezone="$timezone" />
                                </div>
                            @empty
                                <div class="px-1.5 text-xs text-zinc-400">—</div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div>
                <div class="grid grid-cols-7 gap-px text-center text-xs font-medium tracking-wide text-zinc-500 uppercase">
                    @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                        <div class="py-1">{{ $weekday }}</div>
                    @endforeach
                </div>

                <div class="grid grid-cols-7 gap-px overflow-hidden rounded border border-zinc-200 bg-zinc-200 dark:border-zinc-700 dark:bg-zinc-700">
                    @foreach ($days as $day)
                        @php
                            $events = $eventsByDay[$day->toDateString()] ?? [];
                            $shown = array_slice($events, 0, 3);
                        @endphp
                        <button
                            type="button"
                            wire:key="month-{{ $day->toDateString() }}"
                            wire:click="openDay('{{ $day->toDateString() }}')"
                            @class([
                                'min-h-20 cursor-pointer p-1 text-left align-top transition sm:min-h-28 sm:p-1.5',
                                'bg-white hover:bg-zinc-50 dark:bg-zinc-900 dark:hover:bg-zinc-800' => $day->isSameMonth($anchorDay),
                                'bg-zinc-50 text-zinc-400 hover:bg-zinc-100 dark:bg-zinc-950 dark:hover:bg-zinc-800' => ! $day->isSameMonth($anchorDay),
                            ])
                        >
                            <span @class([
                                'inline-block text-sm font-semibold',
                                'text-white bg-zinc-900 dark:bg-white dark:text-zinc-900 rounded-full px-1.5' => $day->isSameDay($today),
                                'text-zinc-900 dark:text-white' => ! $day->isSameDay($today) && $day->isSameMonth($anchorDay),
                                'text-zinc-400 dark:text-zinc-600' => ! $day->isSameDay($today) && ! $day->isSameMonth($anchorDay),
                            ])>{{ $day->format('j') }}</span>

                            @if ($events !== [])
                                {{-- Titles do not fit a phone-width cell, so those get dots. --}}
                                <div class="mt-1 flex gap-0.5 sm:hidden">
                                    @foreach ($shown as $event)
                                        <span class="size-1.5 rounded-full bg-zinc-400 dark:bg-zinc-500"></span>
                                    @endforeach
                                </div>

                                <div class="mt-1 hidden space-y-0.5 sm:block">
                                    @foreach ($shown as $event)
                                        <div @class([
                                            'truncate rounded px-1 py-0.5 text-[11px] leading-tight',
                                            'bg-zinc-200 font-medium text-zinc-800 dark:bg-zinc-700 dark:text-zinc-100' => $event['all_day'],
                                            'text-zinc-700 dark:text-zinc-300' => ! $event['all_day'],
                                        ])>
                                            @unless ($event['all_day'])
                                                <span class="text-zinc-500 dark:text-zinc-400">{{ \Carbon\CarbonImmutable::parse($event['starts_at'])->setTimezone($timezone)->format('g:i') }}</span>
                                            @endunless
                                            {{ $event['title'] }}
                                        </div>
                                    @endforeach

                                    @if (count($events) > 3)
                                        <div class="px-1 text-[11px] text-zinc-500">+{{ count($events) - 3 }} more</div>
                                    @endif
                                </div>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    </flux:card>
</div>
