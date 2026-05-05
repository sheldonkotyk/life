<div class="space-y-6">
    <div class="flex flex-wrap gap-3 items-baseline justify-between">
        <div>
            <flux:heading size="xl">Meal Attendance</flux:heading>
            <flux:text size="sm" variant="subtle">Check the meals you'll be there for in the next 7 days.</flux:text>
        </div>

        @if ($this->members->count() > 1)
            <flux:select wire:model.live="memberId" class:input="w-48">
                @foreach ($this->members as $m)
                    <flux:select.option value="{{ $m->id }}">{{ $m->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
    </div>

    <flux:card class="overflow-x-auto p-0!">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-700">
                    <th class="text-left p-3 font-semibold text-zinc-600 w-32">Day</th>
                    @foreach (\App\Livewire\Availability::SLOTS as $slot)
                        @php
                            $allSlotIn = collect($this->days)->every(
                                fn ($d) => ! in_array($d->toDateString() . '|' . $slot, $this->unavailableKeys)
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
                        $isToday = $d->isToday();
                        $allIn = collect(\App\Livewire\Availability::SLOTS)
                            ->every(fn ($s) => ! in_array($dateStr . '|' . $s, $this->unavailableKeys));
                    @endphp
                    <tr class="border-b border-zinc-100 dark:border-zinc-800 last:border-b-0 {{ $isToday ? 'bg-indigo-50/40 dark:bg-indigo-900/10' : '' }}">
                        <td class="p-3">
                            <div class="font-semibold {{ $isToday ? 'text-indigo-700 dark:text-indigo-300' : '' }}">{{ $d->format('D') }}</div>
                            <div class="text-xs text-zinc-400">{{ $d->format('M j') }}</div>
                        </td>
                        @foreach (\App\Livewire\Availability::SLOTS as $slot)
                            @php $attending = ! in_array($dateStr . '|' . $slot, $this->unavailableKeys); @endphp
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

    <flux:text size="xs" variant="subtle">
        Unchecked meals will exclude you from the meal plan automatically.
    </flux:text>
</div>
