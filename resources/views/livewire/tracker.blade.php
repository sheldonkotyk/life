<div class="space-y-6">
    <div class="flex flex-wrap gap-3 items-baseline justify-between">
        <flux:heading size="xl">Macro Tracker</flux:heading>
        <div class="flex items-center gap-2">
            <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="shiftDay(-1)">Prev</flux:button>
            <flux:button size="sm" wire:click="jumpToToday">Today</flux:button>
            <flux:button size="sm" variant="ghost" icon-trailing="chevron-right" wire:click="shiftDay(1)">Next</flux:button>
            <flux:input type="date" size="sm" wire:model.live="date" />
            <flux:text size="sm" variant="subtle" class="ml-1">{{ $displayDate->format('l, M j') }}</flux:text>
        </div>
    </div>

    @forelse ($members as $m)
        @php
            $c = $consumed[$m->id];
            $rows = [
                ['key' => 'calories', 'label' => 'Calories', 'unit' => 'kcal', 'target' => $m->target_calories, 'color' => 'indigo'],
                ['key' => 'protein_g', 'label' => 'Protein', 'unit' => 'g', 'target' => $m->target_protein_g, 'color' => 'rose'],
                ['key' => 'carbs_g', 'label' => 'Carbs', 'unit' => 'g', 'target' => $m->target_carbs_g, 'color' => 'amber'],
                ['key' => 'fat_g', 'label' => 'Fat', 'unit' => 'g', 'target' => $m->target_fat_g, 'color' => 'emerald'],
            ];
            $hasAnyTarget = $m->target_calories || $m->target_protein_g || $m->target_carbs_g || $m->target_fat_g;
        @endphp
        <flux:card>
            <div class="flex items-center gap-3 mb-3">
                <x-avatar :member="$m" size="lg" />
                <div class="flex-1">
                    <flux:heading size="lg">{{ $m->name }}</flux:heading>
                    <flux:text size="sm" variant="subtle">
                        {{ count($perMemberMeals[$m->id]) }} {{ Str::plural('meal', count($perMemberMeals[$m->id])) }} today
                        @if (! $hasAnyTarget)
                            · <flux:link href="{{ url('/family') }}">set targets</flux:link>
                        @endif
                    </flux:text>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach ($rows as $row)
                    @php
                        $value = round($c[$row['key']], 1);
                        $target = $row['target'];
                        $pct = $target ? min(100, ($value / $target) * 100) : 0;
                        $over = $target && $value > $target;
                        $under = $target && $value < $target;
                        $barClass = $over ? 'bg-red-500' : match ($row['color']) {
                            'indigo' => 'bg-indigo-500',
                            'rose' => 'bg-rose-500',
                            'amber' => 'bg-amber-500',
                            'emerald' => 'bg-emerald-500',
                        };
                    @endphp
                    <div>
                        <div class="flex items-baseline justify-between">
                            <flux:text size="sm" variant="subtle">{{ $row['label'] }}</flux:text>
                            <flux:text size="sm">
                                <span class="font-semibold">{{ $row['key'] === 'calories' ? round($value) : $value }}{{ $row['unit'] }}</span>
                                @if ($target)
                                    <span class="text-zinc-400">/ {{ $row['key'] === 'calories' ? round($target) : $target }}{{ $row['unit'] }}</span>
                                @endif
                            </flux:text>
                        </div>
                        @if ($target)
                            <div class="mt-1.5 h-2 bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                <div class="h-full {{ $barClass }} transition-all" style="width: {{ $pct }}%"></div>
                            </div>
                            <flux:text size="xs" variant="subtle" class="mt-1 block">
                                @if ($over)
                                    {{ round($value - $target, 1) }}{{ $row['unit'] }} over
                                @elseif ($under)
                                    {{ round($target - $value, 1) }}{{ $row['unit'] }} to go
                                @else
                                    on target
                                @endif
                            </flux:text>
                        @else
                            <div class="mt-1.5 h-2 bg-zinc-100 dark:bg-zinc-800 rounded-full"></div>
                            <flux:text size="xs" variant="subtle" class="mt-1 block">no target</flux:text>
                        @endif
                    </div>
                @endforeach
            </div>

            @if (! empty($perMemberMeals[$m->id]))
                <flux:separator class="my-4" />
                <flux:text size="xs" variant="subtle" class="uppercase tracking-wide mb-2 block">Meals</flux:text>
                <div class="space-y-1">
                    @foreach ($perMemberMeals[$m->id] as $meal)
                        <div class="flex items-baseline gap-2 text-sm">
                            <flux:badge size="sm" color="zinc" class="capitalize w-16 justify-center">{{ $meal['slot'] }}</flux:badge>
                            <span class="flex-1">{{ $meal['name'] }}</span>
                            @if ($meal['macros']['calories'] > 0)
                                <flux:text size="xs" variant="subtle">{{ round($meal['macros']['calories']) }} kcal · P{{ $meal['macros']['protein_g'] }} C{{ $meal['macros']['carbs_g'] }} F{{ $meal['macros']['fat_g'] }}</flux:text>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>
    @empty
        <flux:card class="text-center py-12">
            <flux:text variant="subtle">No family members yet.</flux:text>
            <flux:link href="{{ url('/family') }}" class="ml-1">Add some</flux:link>
        </flux:card>
    @endforelse
</div>
