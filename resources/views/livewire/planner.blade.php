<div class="space-y-6">
    <div class="flex flex-wrap gap-3 items-baseline justify-between">
        <flux:heading size="xl">Weekly Plan</flux:heading>
        <div class="flex flex-wrap items-center gap-2">
            <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="shiftWeek(-1)">Prev</flux:button>
            <flux:button size="sm" wire:click="jumpToToday">Today</flux:button>
            <flux:button size="sm" variant="ghost" icon-trailing="chevron-right" wire:click="shiftWeek(1)">Next</flux:button>
            <flux:text size="sm" variant="subtle" class="w-full sm:w-auto sm:ml-2">{{ $start->format('M j') }} – {{ $start->addDays(6)->format('M j, Y') }}</flux:text>
        </div>
    </div>

    {{-- Mobile: stacked by day --}}
    <div class="lg:hidden space-y-3">
        @foreach ($days as $d)
            @php $isToday = $d->toDateString() === $today; @endphp
            <flux:card class="p-0! overflow-hidden">
                <div class="flex items-baseline justify-between px-3 py-2 border-b border-zinc-200 dark:border-zinc-700 {{ $isToday ? 'bg-indigo-50 dark:bg-indigo-900/30' : 'bg-zinc-50 dark:bg-zinc-800/50' }}">
                    <div class="font-semibold {{ $isToday ? 'text-indigo-700 dark:text-indigo-300' : '' }}">{{ $d->format('l') }}</div>
                    <div class="text-xs text-zinc-500">{{ $d->format('M j') }}</div>
                </div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach (['breakfast', 'lunch', 'dinner'] as $slot)
                        @php
                            $key = $d->toDateString() . '|' . $slot;
                            $cellPlans = $plans->get($key, collect());
                        @endphp
                        <div class="px-3 py-2">
                            <div class="text-[10px] uppercase tracking-wide text-zinc-500 font-medium mb-1.5">{{ $slot }}</div>
                            @foreach ($cellPlans as $plan)
                                <button
                                    wire:click="openSlot('{{ $d->toDateString() }}', '{{ $slot }}', {{ $plan->id }})"
                                    class="w-full text-left bg-zinc-50 dark:bg-zinc-800 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded-md p-2 mb-1">
                                    <div class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $plan->displayName() }}</div>
                                    @php $mp = $plan->macrosPerServing(); @endphp
                                    @if ($mp['calories'] > 0)
                                        <div class="text-[11px] text-zinc-500 mt-0.5">{{ round($mp['calories']) }} kcal · P{{ $mp['protein_g'] }} C{{ $mp['carbs_g'] }} F{{ $mp['fat_g'] }}</div>
                                    @endif
                                    <div class="flex flex-wrap gap-0.5 mt-1 items-center">
                                        @foreach ($plan->attendees as $a)
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
                                    class="w-full text-left text-sm text-zinc-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 py-1.5 px-2 rounded">
                                    <div>+ add</div>
                                    @php $defaults = $defaultAttendees[$key] ?? collect(); @endphp
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
                                    class="w-full text-sm text-zinc-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 py-1.5 rounded">
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
                    @foreach ($days as $d)
                        @php $isToday = $d->toDateString() === $today; @endphp
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
                        @foreach ($days as $d)
                            @php
                                $key = $d->toDateString() . '|' . $slot;
                                $cellPlans = $plans->get($key, collect());
                            @endphp
                            <td class="p-1.5 align-top min-w-[140px]">
                                @foreach ($cellPlans as $plan)
                                    <button
                                        wire:click="openSlot('{{ $d->toDateString() }}', '{{ $slot }}', {{ $plan->id }})"
                                        class="w-full text-left bg-zinc-50 dark:bg-zinc-800 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded-md p-2 mb-1">
                                        <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-100 truncate">{{ $plan->displayName() }}</div>
                                        @php $mp = $plan->macrosPerServing(); @endphp
                                        @if ($mp['calories'] > 0)
                                            <div class="text-[10px] text-zinc-500 mt-0.5">{{ round($mp['calories']) }} kcal · P{{ $mp['protein_g'] }} C{{ $mp['carbs_g'] }} F{{ $mp['fat_g'] }}</div>
                                        @endif
                                        <div class="flex flex-wrap gap-0.5 mt-1 items-center">
                                            @foreach ($plan->attendees as $a)
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
                                        class="w-full text-left text-xs text-zinc-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 py-1 px-2 rounded">
                                        <div>+ add</div>
                                        @php $defaults = $defaultAttendees[$key] ?? collect(); @endphp
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
                                        class="w-full text-xs text-zinc-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 py-1 rounded">
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

    {{-- Edit panel --}}
    @if ($editingDate)
        <div class="fixed inset-0 bg-zinc-900/40 z-40" wire:click="cancelEdit"></div>
        <div class="fixed inset-0 sm:inset-y-0 sm:left-auto sm:right-0 sm:w-[480px] bg-white dark:bg-zinc-900 shadow-2xl z-50 flex flex-col">
            <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex justify-between items-center">
                <div>
                    <flux:heading class="capitalize">{{ $editingSlot }}</flux:heading>
                    <flux:text size="sm" variant="subtle">{{ \Carbon\Carbon::parse($editingDate)->format('l, M j') }}</flux:text>
                </div>
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="cancelEdit" />
            </div>

            <div class="flex-1 overflow-y-auto p-4 space-y-4">
                @if ($availableLeftovers->isNotEmpty() && ! $editingPlanId)
                    <div>
                        <flux:text size="xs" variant="subtle" class="uppercase tracking-wide mb-2 block">Use leftovers</flux:text>
                        <div class="space-y-1">
                            @foreach ($availableLeftovers as $lo)
                                <button wire:click="$set('selectedLeftoverId', {{ $lo->id }}); $set('selectedRecipeId', null)"
                                        class="w-full text-left p-2 rounded-md border {{ $selectedLeftoverId === $lo->id ? 'border-amber-400 bg-amber-50 dark:bg-amber-900/20' : 'border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800' }}">
                                    <div class="text-sm font-medium">🥡 {{ $lo->recipe?->name ?? $lo->custom_name }}</div>
                                    <flux:text size="xs" variant="subtle">{{ $lo->date->format('D, M j') }} · {{ $lo->leftover_servings }} servings</flux:text>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <flux:field>
                    <flux:label>Recipe</flux:label>
                    <flux:select wire:model.live="selectedRecipeId" wire:change="$set('selectedLeftoverId', null)">
                        <flux:select.option value="">— None / custom —</flux:select.option>
                        @foreach ($recipes as $r)
                            <flux:select.option value="{{ $r->id }}">{{ $r->name }}@if ($r->makes_leftovers) (leftovers) @endif</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                @if ($activeIngredients->isNotEmpty())
                    @php
                        $totals = ['calories' => 0, 'protein_g' => 0, 'carbs_g' => 0, 'fat_g' => 0];
                        foreach ($activeIngredients as $ing) {
                            if (in_array($ing->id, $skippedIngredientIds)) continue;
                            foreach ($totals as $k => $_) $totals[$k] += (float) ($ing->{$k} ?? 0);
                        }
                        foreach ($totals as $k => $v) $totals[$k] = round($v / $activeRecipeServings, 1);
                    @endphp
                    <div>
                        <flux:text size="xs" variant="subtle" class="uppercase tracking-wide mb-2 block">Ingredients (check to skip)</flux:text>
                        <div class="space-y-1 bg-zinc-50 dark:bg-zinc-800 rounded-md p-2 border border-zinc-200 dark:border-zinc-700">
                            @foreach ($activeIngredients as $ing)
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
                    <div class="grid grid-cols-1 gap-1">
                        @foreach ($members as $m)
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

                <flux:field>
                    <flux:label>Notes</flux:label>
                    <flux:textarea wire:model="notes" rows="2" />
                </flux:field>
            </div>

            <div class="p-4 border-t border-zinc-200 dark:border-zinc-700 flex justify-between gap-2">
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
</div>
