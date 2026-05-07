<div class="space-y-6">
    @php
        $weekStartDate = \Carbon\CarbonImmutable::parse($this->weekStart);
    @endphp
    <div class="flex flex-wrap gap-3 items-baseline justify-between">
        <div>
            <flux:heading size="xl">Meal Attendance</flux:heading>
            <flux:text size="sm" variant="subtle">Check the meals you'll be there for this week.</flux:text>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="shiftWeek(-1)">Prev</flux:button>
            <flux:button size="sm" wire:click="jumpToToday">Today</flux:button>
            <flux:button size="sm" variant="ghost" icon-trailing="chevron-right" wire:click="shiftWeek(1)">Next</flux:button>
            <flux:text size="sm" variant="subtle" class="w-full sm:w-auto sm:ml-2">{{ $weekStartDate->format('M j') }} – {{ $weekStartDate->addDays(6)->format('M j, Y') }}</flux:text>

            @if ($this->members->count() > 1)
                <flux:select wire:model.live="memberId" class:input="w-full sm:w-56 sm:ml-2">
                    @foreach ($this->members as $m)
                        <flux:select.option value="{{ $m->id }}">
                            {{ $m->name }}@if ($m->is_guest) (guest)@endif
                        </flux:select.option>
                    @endforeach
                </flux:select>
            @endif
        </div>
    </div>

    {{-- Mobile: stacked by day --}}
    <div class="lg:hidden space-y-3">
        @foreach ($this->days as $d)
            @php
                $dateStr = $d->toDateString();
                $isToday = $d->toDateString() === $today;
                $slotState = collect(\App\Livewire\Availability::SLOTS)
                    ->mapWithKeys(fn ($s) => [$s => ! in_array($dateStr . '|' . $s, $this->notAttendingKeys)])
                    ->all();
            @endphp
            <div
                wire:key="m-day-{{ $dateStr }}-{{ $this->memberId }}"
                x-data="{
                    slots: @js($slotState),
                    get allIn() { return Object.values(this.slots).every(v => v); },
                    toggle(slot) {
                        this.slots[slot] = !this.slots[slot];
                        $wire.setAttending(@js($dateStr), slot, this.slots[slot]);
                    },
                    setDay(value) {
                        Object.keys(this.slots).forEach(k => this.slots[k] = value);
                        $wire.setDayAttending(@js($dateStr), value);
                    },
                }"
            >
            <flux:card class="p-0! overflow-hidden">
                <div class="flex items-center justify-between px-3 py-2 border-b border-zinc-200 dark:border-zinc-700 {{ $isToday ? 'bg-indigo-50 dark:bg-indigo-900/30' : 'bg-zinc-50 dark:bg-zinc-800/50' }}">
                    <div>
                        <div class="font-semibold {{ $isToday ? 'text-indigo-700 dark:text-indigo-300' : '' }}">{{ $d->format('l') }}</div>
                        <div class="text-xs text-zinc-500">{{ $d->format('M j') }}</div>
                    </div>
                    <flux:button size="xs" variant="ghost" @click="setDay(!allIn)">
                        <span x-text="allIn ? 'Skip day' : 'All in'"></span>
                    </flux:button>
                </div>
                <div class="grid grid-cols-3 divide-x divide-zinc-100 dark:divide-zinc-800">
                    @foreach (\App\Livewire\Availability::SLOTS as $slot)
                        <button
                            type="button"
                            @click="toggle(@js($slot))"
                            :class="slots[@js($slot)] ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300 font-medium' : 'text-zinc-400 dark:text-zinc-500'"
                            class="py-3 px-2 text-center capitalize text-sm transition">
                            <div class="text-lg leading-none mb-1" x-text="slots[@js($slot)] ? '✓' : '–'"></div>
                            {{ $slot }}
                        </button>
                    @endforeach
                </div>
            </flux:card>
            </div>
        @endforeach
    </div>

    {{-- Desktop: weekly grid --}}
    <div class="hidden lg:block">
    <flux:card class="overflow-x-auto p-0!">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-700">
                    <th class="text-left p-3 font-semibold text-zinc-600 w-32">Day</th>
                    @foreach (\App\Livewire\Availability::SLOTS as $slot)
                        @php
                            $allSlotIn = collect($this->days)->every(
                                fn ($d) => ! in_array($d->toDateString() . '|' . $slot, $this->notAttendingKeys)
                            );
                        @endphp
                        <th class="text-center p-3 font-semibold text-zinc-600 dark:text-zinc-300 capitalize">
                            <div>{{ $slot }}</div>
                            <flux:button size="xs" class="mt-1" variant="ghost"
                                wire:click="setSlotAttending('{{ $slot }}', {{ $allSlotIn ? 'false' : 'true' }})">
                                {{ $allSlotIn ? 'Skip all' : 'All in' }}
                            </flux:button>
                        </th>
                    @endforeach
                    <th class="text-right p-3 font-semibold text-zinc-500 w-24">All day</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->days as $d)
                    @php
                        $dateStr = $d->toDateString();
                        $isToday = $d->toDateString() === $today;
                        $allIn = collect(\App\Livewire\Availability::SLOTS)
                            ->every(fn ($s) => ! in_array($dateStr . '|' . $s, $this->notAttendingKeys));
                    @endphp
                    <tr class="border-b border-zinc-100 dark:border-zinc-800 last:border-b-0 {{ $isToday ? 'bg-indigo-50/40 dark:bg-indigo-900/10' : '' }}">
                        <td class="p-3">
                            <div class="font-semibold {{ $isToday ? 'text-indigo-700 dark:text-indigo-300' : '' }}">{{ $d->format('D') }}</div>
                            <div class="text-xs text-zinc-400">{{ $d->format('M j') }}</div>
                        </td>
                        @foreach (\App\Livewire\Availability::SLOTS as $slot)
                            @php $attending = ! in_array($dateStr . '|' . $slot, $this->notAttendingKeys); @endphp
                            <td class="p-3 text-center">
                                <label class="inline-flex items-center justify-center cursor-pointer">
                                    <flux:checkbox
                                        wire:key="cell-{{ $dateStr }}-{{ $slot }}-{{ $attending ? '1' : '0' }}"
                                        :checked="$attending"
                                        wire:click="setAttending('{{ $dateStr }}', '{{ $slot }}', {{ $attending ? 'false' : 'true' }})"
                                    />
                                </label>
                            </td>
                        @endforeach
                        <td class="p-3 text-right">
                            <flux:button size="xs" variant="ghost" wire:click="setDayAttending('{{ $dateStr }}', {{ $allIn ? 'false' : 'true' }})">
                                {{ $allIn ? 'Skip' : 'All in' }}
                            </flux:button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </flux:card>
    </div>

    <flux:text size="xs" variant="subtle">
        Unchecked meals will exclude you from the meal plan automatically.
    </flux:text>
</div>
