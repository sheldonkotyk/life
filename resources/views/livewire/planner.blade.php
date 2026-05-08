<div class="space-y-6"
    x-data="{ mode: @js($mode) }"
    x-init="$watch('mode', v => { if ($wire.mode !== v) $wire.set('mode', v) })">
    <div class="flex flex-wrap gap-3 items-baseline justify-between">
        <div>
            <flux:heading size="xl">Meal Plan</flux:heading>
            <flux:text size="sm" variant="subtle">
                <span x-show="mode === 'plan'">Plan meals and mark who's eating.</span>
                <span x-show="mode === 'attendance'" x-cloak>Check the meals you'll be there for this week.</span>
            </flux:text>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="shiftWeek(-1)">Prev</flux:button>
            <flux:button size="sm" wire:click="jumpToToday">Today</flux:button>
            <flux:button size="sm" variant="ghost" icon-trailing="chevron-right" wire:click="shiftWeek(1)">Next</flux:button>
            <flux:text size="sm" variant="subtle" class="w-full sm:w-auto sm:ml-2">{{ $this->start->format('M j') }} – {{ $this->start->addDays(6)->format('M j, Y') }}</flux:text>
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="inline-flex items-center bg-zinc-100 dark:bg-zinc-800 rounded-lg p-1 text-sm font-medium">
            <button type="button" @click="mode = 'plan'"
                :class="mode === 'plan' ? 'bg-white dark:bg-zinc-900 shadow-sm text-zinc-900 dark:text-zinc-100' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100'"
                class="px-4 py-1.5 rounded-md transition">
                <flux:icon icon="calendar-days" variant="micro" class="inline -mt-0.5 mr-1" /> Plan
            </button>
            <button type="button" @click="mode = 'attendance'"
                :class="mode === 'attendance' ? 'bg-white dark:bg-zinc-900 shadow-sm text-zinc-900 dark:text-zinc-100' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100'"
                class="px-4 py-1.5 rounded-md transition">
                <flux:icon icon="user-group" variant="micro" class="inline -mt-0.5 mr-1" /> Attendance
            </button>
        </div>

        @if ($this->selectableMembers->count() > 1)
            @php $memberMap = $this->selectableMembers->keyBy('id'); @endphp
            <div x-data="{ selected: @js($memberId) }" x-show="mode === 'attendance'" x-cloak>
                <flux:dropdown align="end">
                    <flux:button variant="outline" size="sm" icon-trailing="chevron-down" class="!items-center !gap-2 !pl-2 !pr-3 !pt-3 !pb-2">
                        @foreach ($memberMap as $id => $m)
                            <span x-show="selected === {{ $id }}" class="inline-flex items-center gap-2">
                                <x-avatar :member="$m" size="sm" class="!ring-0 !shadow-none" />
                                <span class="text-sm font-medium">{{ $m->name }}</span>
                                @if ($m->is_guest)
                                    <span class="text-[10px] uppercase tracking-wide text-zinc-500">guest</span>
                                @endif
                            </span>
                        @endforeach
                    </flux:button>
                    <flux:menu>
                        @foreach ($memberMap as $id => $m)
                            <flux:menu.item
                                as="button"
                                wire:click="selectMember({{ $id }})"
                                wire:island="attendance"
                                x-on:click="selected = {{ $id }}">
                                <div class="flex items-center gap-2 w-full">
                                    <x-avatar :member="$m" size="sm" />
                                    <span class="flex-1 text-left">{{ $m->name }}</span>
                                    @if ($m->is_guest)
                                        <span class="text-[10px] uppercase tracking-wide text-zinc-500">guest</span>
                                    @endif
                                    <flux:icon icon="check" variant="micro" x-show="selected === {{ $id }}" class="text-indigo-600" />
                                </div>
                            </flux:menu.item>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>
            </div>
        @endif
    </div>

    {{-- ============ ATTENDANCE ISLAND ============ --}}
    <div x-show="mode === 'attendance'" x-cloak>
    @island(name: 'attendance', always: true)
        <div class="space-y-4">
            {{-- Mobile: stacked by day --}}
            <div class="lg:hidden space-y-3">
                @foreach ($this->days as $d)
                    @php
                        $dateStr = $d->toDateString();
                        $isToday = $dateStr === $this->today;
                        $allIn = collect(\App\Livewire\Planner::SLOTS)
                            ->every(fn ($s) => ! in_array($dateStr.'|'.$s, $this->notAttendingKeys));
                    @endphp
                    <flux:card class="p-0! overflow-hidden" wire:key="att-day-{{ $dateStr }}-{{ $this->memberId }}">
                        <div class="flex items-center justify-between px-3 py-2 border-b border-zinc-200 dark:border-zinc-700 {{ $isToday ? 'bg-indigo-50 dark:bg-indigo-900/30' : 'bg-zinc-50 dark:bg-zinc-800/50' }}">
                            <div>
                                <div class="font-semibold {{ $isToday ? 'text-indigo-700 dark:text-indigo-300' : '' }}">{{ $d->format('l') }}</div>
                                <div class="text-xs text-zinc-500">{{ $d->format('M j') }}</div>
                            </div>
                            <flux:button size="xs" variant="ghost"
                                wire:click="setDayAttending('{{ $dateStr }}', {{ $allIn ? 'false' : 'true' }})"
                                wire:island="attendance">
                                {{ $allIn ? 'Skip day' : 'All in' }}
                            </flux:button>
                        </div>
                        <div class="grid grid-cols-3 divide-x divide-zinc-100 dark:divide-zinc-800">
                            @foreach (\App\Livewire\Planner::SLOTS as $slot)
                                @php $attending = ! in_array($dateStr.'|'.$slot, $this->notAttendingKeys); @endphp
                                <button
                                    type="button"
                                    wire:click="setAttending('{{ $dateStr }}', '{{ $slot }}', {{ $attending ? 'false' : 'true' }})"
                                    wire:island="attendance"
                                    class="py-3 px-2 text-center capitalize text-sm transition {{ $attending ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300 font-medium' : 'text-zinc-400 dark:text-zinc-500' }}">
                                    <div class="text-lg leading-none mb-1">{{ $attending ? '✓' : '–' }}</div>
                                    {{ $slot }}
                                </button>
                            @endforeach
                        </div>
                    </flux:card>
                @endforeach
            </div>

            {{-- Desktop: weekly grid --}}
            <div class="hidden lg:block">
                <flux:card class="overflow-x-auto p-0!">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-700">
                                <th class="text-left p-3 font-semibold text-zinc-600 w-32">Day</th>
                                @foreach (\App\Livewire\Planner::SLOTS as $slot)
                                    @php
                                        $allSlotIn = collect($this->days)->every(
                                            fn ($d) => ! in_array($d->toDateString().'|'.$slot, $this->notAttendingKeys)
                                        );
                                    @endphp
                                    <th class="text-center p-3 font-semibold text-zinc-600 dark:text-zinc-300 capitalize">
                                        <div>{{ $slot }}</div>
                                        <flux:button size="xs" class="mt-1" variant="ghost"
                                            wire:click="setSlotAttending('{{ $slot }}', {{ $allSlotIn ? 'false' : 'true' }})"
                                            wire:island="attendance">
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
                                    $isToday = $dateStr === $this->today;
                                    $allIn = collect(\App\Livewire\Planner::SLOTS)
                                        ->every(fn ($s) => ! in_array($dateStr.'|'.$s, $this->notAttendingKeys));
                                @endphp
                                <tr class="border-b border-zinc-100 dark:border-zinc-800 last:border-b-0 {{ $isToday ? 'bg-indigo-50/40 dark:bg-indigo-900/10' : '' }}">
                                    <td class="p-3">
                                        <div class="font-semibold {{ $isToday ? 'text-indigo-700 dark:text-indigo-300' : '' }}">{{ $d->format('D') }}</div>
                                        <div class="text-xs text-zinc-400">{{ $d->format('M j') }}</div>
                                    </td>
                                    @foreach (\App\Livewire\Planner::SLOTS as $slot)
                                        @php $attending = ! in_array($dateStr.'|'.$slot, $this->notAttendingKeys); @endphp
                                        <td class="p-3 text-center">
                                            <label class="inline-flex items-center justify-center cursor-pointer">
                                                <flux:checkbox
                                                    wire:key="att-cell-{{ $dateStr }}-{{ $slot }}-{{ $attending ? '1' : '0' }}"
                                                    :checked="$attending"
                                                    wire:click="setAttending('{{ $dateStr }}', '{{ $slot }}', {{ $attending ? 'false' : 'true' }})"
                                                    wire:island="attendance"
                                                />
                                            </label>
                                        </td>
                                    @endforeach
                                    <td class="p-3 text-right">
                                        <flux:button size="xs" variant="ghost"
                                            wire:click="setDayAttending('{{ $dateStr }}', {{ $allIn ? 'false' : 'true' }})"
                                            wire:island="attendance">
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
    @endisland
    </div>

    {{-- ============ PLAN ISLAND ============ --}}
    <div x-show="mode === 'plan'">
    @island(name: 'plan', always: true)
        <div class="space-y-3 lg:space-y-0">
            {{-- Mobile: stacked by day --}}
            <div class="lg:hidden space-y-3">
                @foreach ($this->days as $d)
                    @php $isToday = $d->toDateString() === $this->today; @endphp
                    <flux:card class="p-0! overflow-hidden">
                        <div class="flex items-baseline justify-between px-3 py-2 border-b border-zinc-200 dark:border-zinc-700 {{ $isToday ? 'bg-indigo-50 dark:bg-indigo-900/30' : 'bg-zinc-50 dark:bg-zinc-800/50' }}">
                            <div class="font-semibold {{ $isToday ? 'text-indigo-700 dark:text-indigo-300' : '' }}">{{ $d->format('l') }}</div>
                            <div class="text-xs text-zinc-500">{{ $d->format('M j') }}</div>
                        </div>
                        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach (['breakfast', 'lunch', 'dinner'] as $slot)
                                @php
                                    $key = $d->toDateString().'|'.$slot;
                                    $cellPlans = $this->plans->get($key, collect());
                                @endphp
                                <div class="px-3 py-2">
                                    <div class="text-[10px] uppercase tracking-wide text-zinc-500 font-medium mb-1.5">{{ $slot }}</div>
                                    @foreach ($cellPlans as $plan)
                                        <button
                                            wire:click="openSlot('{{ $d->toDateString() }}', '{{ $slot }}', {{ $plan->id }})"
                                            class="w-full text-left bg-zinc-50 dark:bg-zinc-800 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded-md p-2 mb-1">
                                            <div class="text-sm font-semibold text-zinc-800 dark:text-zinc-100 break-words">{{ $plan->displayName() }}</div>
                                            @php $mp = $plan->macrosPerServing(); @endphp
                                            @if ($mp['calories'] > 0)
                                                <div class="text-[11px] text-zinc-500 mt-0.5">{{ round($mp['calories']) }} kcal · P{{ $mp['protein_g'] }} C{{ $mp['carbs_g'] }} F{{ $mp['fat_g'] }}</div>
                                            @endif
                                            <div class="flex flex-wrap gap-0.5 mt-1 items-center">
                                                @foreach ($plan->confirmedAttendees() as $a)
                                                    <x-avatar :member="$a" size="xs" />
                                                @endforeach
                                                @if ($plan->save_leftovers)
                                                    <span class="ml-auto text-[11px] text-amber-600">🥡{{ $plan->leftover_servings }}</span>
                                                @endif
                                            </div>
                                        </button>
                                    @endforeach
                                    @if ($cellPlans->isEmpty())
                                        <button
                                            wire:click="openSlot('{{ $d->toDateString() }}', '{{ $slot }}')"
                                            class="w-full text-left text-base font-medium text-zinc-500 dark:text-zinc-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 border border-dashed border-zinc-300 dark:border-zinc-700 py-3 px-3 rounded-md">
                                            <div>+ add</div>
                                            @php $defaults = $this->defaultAttendees[$key] ?? collect(); @endphp
                                            @if ($defaults->isNotEmpty())
                                                <div class="flex flex-wrap gap-0.5 mt-1">
                                                    @foreach ($defaults as $a)
                                                        <x-avatar :member="$a" size="xs" />
                                                    @endforeach
                                                </div>
                                            @endif
                                        </button>
                                    @else
                                        <button
                                            wire:click="openSlot('{{ $d->toDateString() }}', '{{ $slot }}')"
                                            class="w-full text-base font-medium text-zinc-500 dark:text-zinc-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 border border-dashed border-zinc-300 dark:border-zinc-700 py-2.5 rounded-md mt-1">
                                            + add
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </flux:card>
                @endforeach
            </div>

            {{-- Desktop: weekly grid --}}
            <div class="hidden lg:block">
                <flux:card class="overflow-x-auto p-0!">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-700">
                                <th class="text-left p-2 font-semibold text-zinc-600 w-24"></th>
                                @foreach ($this->days as $d)
                                    @php $isToday = $d->toDateString() === $this->today; @endphp
                                    <th class="text-left p-2 font-semibold {{ $isToday ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300' : 'text-zinc-600 dark:text-zinc-300' }}">
                                        <div>{{ $d->format('D') }}</div>
                                        <div class="text-xs font-normal text-zinc-400">{{ $d->format('M j') }}</div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (['breakfast', 'lunch', 'dinner'] as $slot)
                                <tr class="border-b border-zinc-100 dark:border-zinc-800 last:border-b-0">
                                    <td class="p-2 align-top text-zinc-500 font-medium uppercase tracking-wide text-xs pt-3 capitalize">{{ $slot }}</td>
                                    @foreach ($this->days as $d)
                                        @php
                                            $key = $d->toDateString().'|'.$slot;
                                            $cellPlans = $this->plans->get($key, collect());
                                        @endphp
                                        <td class="p-1.5 align-top min-w-[140px]">
                                            @foreach ($cellPlans as $plan)
                                                <button
                                                    wire:click="openSlot('{{ $d->toDateString() }}', '{{ $slot }}', {{ $plan->id }})"
                                                    class="w-full text-left bg-zinc-50 dark:bg-zinc-800 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded-md p-2 mb-1">
                                                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-100 break-words">{{ $plan->displayName() }}</div>
                                                    @php $mp = $plan->macrosPerServing(); @endphp
                                                    @if ($mp['calories'] > 0)
                                                        <div class="text-[10px] text-zinc-500 mt-0.5">{{ round($mp['calories']) }} kcal · P{{ $mp['protein_g'] }} C{{ $mp['carbs_g'] }} F{{ $mp['fat_g'] }}</div>
                                                    @endif
                                                    <div class="flex flex-wrap gap-0.5 mt-1 items-center">
                                                        @foreach ($plan->confirmedAttendees() as $a)
                                                            <x-avatar :member="$a" size="xs" />
                                                        @endforeach
                                                        @if ($plan->save_leftovers)
                                                            <span class="ml-auto text-[10px] text-amber-600">🥡{{ $plan->leftover_servings }}</span>
                                                        @endif
                                                    </div>
                                                </button>
                                            @endforeach
                                            @if ($cellPlans->isEmpty())
                                                <button
                                                    wire:click="openSlot('{{ $d->toDateString() }}', '{{ $slot }}')"
                                                    class="w-full text-left text-sm font-medium text-zinc-500 dark:text-zinc-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 border border-dashed border-zinc-300 dark:border-zinc-700 py-2.5 px-2 rounded-md">
                                                    <div>+ add</div>
                                                    @php $defaults = $this->defaultAttendees[$key] ?? collect(); @endphp
                                                    @if ($defaults->isNotEmpty())
                                                        <div class="flex flex-wrap gap-0.5 mt-1">
                                                            @foreach ($defaults as $a)
                                                                <x-avatar :member="$a" size="xs" />
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </button>
                                            @else
                                                <button
                                                    wire:click="openSlot('{{ $d->toDateString() }}', '{{ $slot }}')"
                                                    class="w-full text-sm font-medium text-zinc-500 dark:text-zinc-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 border border-dashed border-zinc-300 dark:border-zinc-700 py-2 rounded-md mt-1">
                                                    + add
                                                </button>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </flux:card>
            </div>
        </div>
    @endisland
    </div>

    {{-- Edit modal --}}
    <flux:modal name="edit-meal" @close="cancelEdit" class="md:max-w-3xl md:w-[48rem]">
        @if ($editingDate)
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg" class="capitalize">{{ $editingSlot }}</flux:heading>
                    <flux:text size="sm" variant="subtle">{{ \Carbon\Carbon::parse($editingDate)->format('l, M j') }}</flux:text>
                </div>

                @if ($this->availableLeftovers->isNotEmpty() && ! $editingPlanId)
                    @php $allLeftoverIds = $this->availableLeftovers->pluck('id')->all(); @endphp
                    <div>
                        <div class="flex items-baseline justify-between mb-2">
                            <flux:text size="xs" variant="subtle" class="uppercase tracking-wide">Use leftovers</flux:text>
                            <div class="flex gap-3 text-xs">
                                @if (count($selectedLeftoverIds) < count($allLeftoverIds))
                                    <button type="button" wire:click="selectAllLeftovers({{ json_encode($allLeftoverIds) }})" class="text-amber-600 hover:text-amber-700">Select all</button>
                                @endif
                                @if (! empty($selectedLeftoverIds))
                                    <button type="button" wire:click="clearLeftovers" class="text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">Clear</button>
                                @endif
                            </div>
                        </div>
                        <div class="space-y-1">
                            @foreach ($this->availableLeftovers as $lo)
                                @php $checked = in_array($lo->id, $selectedLeftoverIds); @endphp
                                <button type="button" wire:click="toggleLeftover({{ $lo->id }})"
                                        class="w-full text-left p-2 rounded-md border {{ $checked ? 'border-amber-400 bg-amber-50 dark:bg-amber-900/20' : 'border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800' }}">
                                    <div class="text-sm font-medium">🥡 {{ $lo->recipe?->name ?? $lo->custom_name }}</div>
                                    <flux:text size="xs" variant="subtle">{{ $lo->date->format('D, M j') }} · {{ $lo->leftover_servings }} servings</flux:text>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <flux:field>
                    <flux:label>Recipe</flux:label>
                    <flux:select wire:model.live="selectedRecipeId" wire:change="clearLeftovers">
                        <flux:select.option value="">— None / custom —</flux:select.option>
                        @foreach ($this->recipes as $r)
                            <flux:select.option value="{{ $r->id }}">{{ $r->name }}@if ($r->makes_leftovers) (leftovers) @endif</flux:select.option>
                        @endforeach
                    </flux:select>
                    <div class="flex gap-2 mt-2">
                        <flux:input
                            class:input="grow"
                            wire:model="newRecipeName"
                            wire:keydown.enter.prevent="createRecipeFromName"
                            placeholder="New recipe name"
                        />
                        <flux:button icon="plus" wire:click="createRecipeFromName">Create</flux:button>
                    </div>
                </flux:field>

                @if ($this->activeIngredients->isNotEmpty())
                    @php
                        $totals = ['calories' => 0, 'protein_g' => 0, 'carbs_g' => 0, 'fat_g' => 0];
                        foreach ($this->activeIngredients as $ing) {
                            if (in_array($ing->id, $skippedIngredientIds)) continue;
                            foreach ($totals as $k => $_) $totals[$k] += (float) ($ing->{$k} ?? 0);
                        }
                        foreach ($totals as $k => $v) $totals[$k] = round($v / $this->activeRecipeServings, 1);
                    @endphp
                    <div>
                        <flux:text size="xs" variant="subtle" class="uppercase tracking-wide mb-2 block">Ingredients (check to skip)</flux:text>
                        <div class="space-y-1 bg-zinc-50 dark:bg-zinc-800 rounded-md p-2 border border-zinc-200 dark:border-zinc-700">
                            @foreach ($this->activeIngredients as $ing)
                                <label class="flex items-center gap-2 px-1 py-0.5 hover:bg-white dark:hover:bg-zinc-700 rounded cursor-pointer text-sm">
                                    <flux:checkbox wire:model.live="skippedIngredientIds" value="{{ $ing->id }}" />
                                    <span class="flex-1 {{ in_array($ing->id, $skippedIngredientIds) ? 'line-through text-zinc-400' : '' }}">
                                        {{ trim(($ing->quantity ?? '') . ' ' . ($ing->unit ?? '')) }} {{ $ing->name }}
                                    </span>
                                    @if ($ing->calories)
                                        <flux:text size="xs" variant="subtle">{{ round($ing->calories) }} kcal</flux:text>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                        <div class="mt-2 grid grid-cols-4 gap-1">
                            @foreach ([
                                ['label' => 'kcal', 'value' => round($totals['calories'])],
                                ['label' => 'P', 'value' => $totals['protein_g'] . 'g'],
                                ['label' => 'C', 'value' => $totals['carbs_g'] . 'g'],
                                ['label' => 'F', 'value' => $totals['fat_g'] . 'g'],
                            ] as $stat)
                                <div class="bg-white dark:bg-zinc-900 rounded px-2 py-1 border border-zinc-200 dark:border-zinc-700 text-center">
                                    <flux:text size="xs" variant="subtle">{{ $stat['label'] }}</flux:text>
                                    <div class="text-sm font-semibold">{{ $stat['value'] }}</div>
                                </div>
                            @endforeach
                        </div>
                        <flux:text size="xs" variant="subtle" class="mt-1 text-center block">per serving, skipped ingredients excluded</flux:text>
                    </div>
                @endif

                <flux:field>
                    <flux:label>Or just a name</flux:label>
                    <flux:input wire:model="customName" placeholder="e.g. Pizza night" />
                </flux:field>

                <div>
                    <flux:text size="xs" variant="subtle" class="uppercase tracking-wide mb-2 block">Who's eating?</flux:text>
                    @php $hasGuests = $this->members->contains('is_guest', true); $guestDividerShown = false; @endphp
                    <div class="grid grid-cols-1 gap-1">
                        @foreach ($this->members as $m)
                            @if ($m->is_guest && ! $guestDividerShown)
                                @php $guestDividerShown = true; @endphp
                                <div class="flex items-center gap-2 mt-2 mb-1">
                                    <flux:text size="xs" variant="subtle" class="uppercase tracking-wide">Guests</flux:text>
                                    <flux:separator class="flex-1" />
                                </div>
                            @endif
                            <label class="flex items-center gap-2 p-2 rounded hover:bg-zinc-50 dark:hover:bg-zinc-800 cursor-pointer">
                                <flux:checkbox wire:model="attendees" value="{{ $m->id }}" />
                                <x-avatar :member="$m" size="sm" />
                                <span class="text-sm">{{ $m->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <flux:callout color="amber" icon="archive-box">
                    <div>
                        <flux:checkbox wire:model.live="saveLeftovers" label="Save leftovers from this meal" />
                        @if ($saveLeftovers)
                            <div class="mt-2 flex items-center gap-2">
                                <flux:label>Servings to save</flux:label>
                                <flux:input type="number" wire:model="leftoverServings" min="1" class:input="w-20" size="sm" />
                            </div>
                        @endif
                    </div>
                </flux:callout>

                <div class="grid grid-cols-2 gap-2">
                    <flux:input type="time" wire:model="startTime" label="Start time (override)" placeholder="default" />
                    <flux:input type="time" wire:model="endTime" label="End time (override)" placeholder="default" />
                </div>
                @error('endTime') <flux:text size="sm" class="text-red-600">{{ $message }}</flux:text> @enderror

                <flux:field>
                    <flux:label>Notes</flux:label>
                    <flux:textarea wire:model="notes" rows="2" />
                </flux:field>

                <div class="flex justify-between gap-2 pt-2">
                    <div>
                        @if ($editingPlanId)
                            <flux:button variant="danger" wire:click="clearPlan" wire:confirm="Remove this meal?">Remove</flux:button>
                        @endif
                    </div>
                    <div class="flex gap-2">
                        <flux:button variant="ghost" wire:click="cancelEdit">Cancel</flux:button>
                        <flux:button variant="primary" wire:click="savePlan">Save</flux:button>
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
