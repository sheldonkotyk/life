<div class="space-y-4 max-w-3xl mx-auto">
    <div class="flex items-baseline justify-between">
        <flux:heading size="xl">Today</flux:heading>
        <flux:text size="sm" variant="subtle">{{ $today->format('l, M j') }}</flux:text>
    </div>

    @if ($meals->isEmpty())
        <flux:card>
            <flux:heading size="lg">Nothing planned today</flux:heading>
            <flux:text class="mt-1">Plan a meal to get started.</flux:text>
            <div class="mt-4">
                <flux:button :href="url('/plan?date=' . $today->toDateString() . '&slot=dinner')" variant="primary">Plan today's meals</flux:button>
            </div>
        </flux:card>
    @else
        @foreach ($meals as $m)
            @php
                $plan = $m['plan'];
                $recipe = $m['recipe'];
                $prepMinutes = $m['prepMinutes'];
                $statuses = $m['statuses'];
                $confirmedCount = $m['confirmedCount'];
                $lateCount = $m['lateCount'];
                $perServing = $m['perServing'];
                $scaledMacros = $m['scaledMacros'];
                $servings = max(1, (int) ($recipe?->servings ?? 1));
                $hasMacros = $perServing && ($perServing['calories'] ?? 0) > 0;
                $myStatus = $myMember && isset($statuses[$myMember->id]) ? $statuses[$myMember->id] : null;
            @endphp

            <flux:card class="overflow-hidden p-0!">
                <div class="p-5 sm:p-6 bg-gradient-to-br from-indigo-50 to-amber-50 dark:from-indigo-950/40 dark:to-amber-950/30">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs uppercase tracking-wider text-indigo-700 dark:text-indigo-300 font-semibold">{{ ucfirst($plan->slot) }}</div>
                            <flux:heading size="xl" class="mt-1 break-words">{{ $plan->displayName() }}</flux:heading>
                            <flux:text size="sm" class="mt-1">
                                Serves {{ $servings }} — <span class="font-semibold text-zinc-800 dark:text-zinc-100">{{ $confirmedCount }} confirmed</span>
                                @if ($lateCount > 0)
                                    <span class="text-amber-700 dark:text-amber-400">({{ $lateCount }} running late)</span>
                                @endif
                            </flux:text>
                        </div>
                        @if ($prepMinutes)
                            <flux:badge color="indigo" size="lg">{{ $prepMinutes }} min</flux:badge>
                        @endif
                    </div>

                    @if ($hasMacros)
                        <div class="mt-4 grid grid-cols-4 gap-2 text-center">
                            @foreach ([
                                ['kcal', $scaledMacros['calories'] ?? 0, $perServing['calories']],
                                ['protein', $scaledMacros['protein_g'] ?? 0, $perServing['protein_g']],
                                ['carbs', $scaledMacros['carbs_g'] ?? 0, $perServing['carbs_g']],
                                ['fat', $scaledMacros['fat_g'] ?? 0, $perServing['fat_g']],
                            ] as [$label, $total, $each])
                                <div class="rounded-lg bg-white/70 dark:bg-zinc-900/40 p-2">
                                    <div class="text-base font-bold text-zinc-800 dark:text-zinc-100">{{ round($total) }}</div>
                                    <div class="text-[10px] uppercase tracking-wide text-zinc-500">{{ $label }}</div>
                                    <div class="text-[10px] text-zinc-400">{{ round($each) }}/srv</div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($prepMinutes)
                        <div x-data="cookTimer({{ $prepMinutes }})" class="mt-4">
                            <div class="flex items-center gap-2">
                                <flux:button x-show="!running && remaining === total * 60" wire:ignore variant="primary" icon="play" x-on:click="start()">
                                    Start Cooking
                                </flux:button>
                                <flux:button x-show="running" wire:ignore variant="filled" icon="pause" x-on:click="pause()">
                                    <span x-text="formatted"></span>
                                </flux:button>
                                <flux:button x-show="!running && remaining !== total * 60" wire:ignore variant="filled" icon="play" x-on:click="start()">
                                    Resume <span x-text="formatted" class="ml-1"></span>
                                </flux:button>
                                <flux:button x-show="remaining !== total * 60" wire:ignore variant="ghost" icon="arrow-path" x-on:click="reset()">
                                    Reset
                                </flux:button>
                            </div>
                        </div>
                    @endif
                </div>

                @if ($members->isNotEmpty())
                    <div class="border-t border-zinc-200 dark:border-zinc-700 p-4 sm:p-5">
                        <div class="text-xs uppercase tracking-wider text-zinc-500 font-semibold mb-3">Attendance</div>

                        @if ($myMember)
                            <div class="mb-4 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                                <div class="flex items-center gap-2 mb-2">
                                    <x-avatar :member="$myMember" size="sm" />
                                    <flux:text size="sm" class="font-semibold">You</flux:text>
                                </div>
                                <div class="grid grid-cols-3 gap-2">
                                    <flux:button size="sm"
                                        :variant="$myStatus === 'eating' ? 'primary' : 'filled'"
                                        wire:click="setMyStatus({{ $plan->id }}, 'eating')">
                                        🍽 I'm eating
                                    </flux:button>
                                    <flux:button size="sm"
                                        :variant="$myStatus === 'running_late' ? 'primary' : 'filled'"
                                        wire:click="setMyStatus({{ $plan->id }}, 'running_late')">
                                        ⏰ Late
                                    </flux:button>
                                    <flux:button size="sm"
                                        :variant="$myStatus === 'not_eating' ? 'primary' : 'filled'"
                                        wire:click="setMyStatus({{ $plan->id }}, 'not_eating')">
                                        ✕ Skip
                                    </flux:button>
                                </div>
                            </div>
                        @endif

                        <div class="space-y-2">
                            @foreach ($members as $member)
                                @if ($myMember && $member->id === $myMember->id)
                                    @continue
                                @endif
                                @php $s = $statuses[$member->id] ?? 'not_eating'; @endphp
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <x-avatar :member="$member" size="sm" />
                                        <flux:text size="sm" class="truncate">{{ $member->name }}</flux:text>
                                    </div>
                                    <div class="flex gap-1">
                                        <flux:button size="xs"
                                            :variant="$s === 'eating' ? 'primary' : 'ghost'"
                                            wire:click="setMemberStatus({{ $plan->id }}, {{ $member->id }}, 'eating')">🍽</flux:button>
                                        <flux:button size="xs"
                                            :variant="$s === 'running_late' ? 'primary' : 'ghost'"
                                            wire:click="setMemberStatus({{ $plan->id }}, {{ $member->id }}, 'running_late')">⏰</flux:button>
                                        <flux:button size="xs"
                                            :variant="$s === 'not_eating' ? 'primary' : 'ghost'"
                                            wire:click="setMemberStatus({{ $plan->id }}, {{ $member->id }}, 'not_eating')">✕</flux:button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($plan->notes)
                    <div class="border-t border-zinc-200 dark:border-zinc-700 p-4 sm:p-5 bg-amber-50/50 dark:bg-amber-950/20">
                        <div class="text-xs uppercase tracking-wider text-amber-700 dark:text-amber-400 font-semibold mb-1">Note</div>
                        <flux:text size="sm">{{ $plan->notes }}</flux:text>
                    </div>
                @endif
            </flux:card>
        @endforeach

        @if ($unplannedSlots->isNotEmpty())
            <flux:card>
                <flux:text size="sm" variant="subtle">Not planned yet:</flux:text>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($unplannedSlots as $slot)
                        <flux:button size="sm" variant="ghost" :href="url('/plan?date=' . $today->toDateString() . '&slot=' . $slot)">
                            + {{ ucfirst($slot) }}
                        </flux:button>
                    @endforeach
                </div>
            </flux:card>
        @endif
    @endif

    @if ($leftovers->isNotEmpty())
        <flux:card>
            <div class="flex items-start gap-3">
                <div class="text-2xl">🥡</div>
                <div class="flex-1">
                    <flux:heading size="md">Use it up</flux:heading>
                    <flux:text size="sm" class="mt-1">Leftovers waiting in the fridge:</flux:text>
                    <ul class="mt-2 space-y-1">
                        @foreach ($leftovers as $l)
                            <li class="text-sm">
                                <span class="font-semibold">{{ $l->displayName() }}</span>
                                <span class="text-zinc-500">— {{ $l->leftover_servings }} servings from {{ $l->date->format('D M j') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </flux:card>
    @endif
</div>

@script
<script>
    Alpine.data('cookTimer', (minutes) => ({
        total: minutes,
        remaining: minutes * 60,
        running: false,
        interval: null,
        get formatted() {
            const m = Math.floor(this.remaining / 60);
            const s = this.remaining % 60;
            return `${m}:${String(s).padStart(2, '0')}`;
        },
        start() {
            this.running = true;
            this.interval = setInterval(() => {
                if (this.remaining > 0) {
                    this.remaining--;
                } else {
                    this.pause();
                    try { new Audio('data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=').play(); } catch (e) {}
                }
            }, 1000);
        },
        pause() {
            this.running = false;
            clearInterval(this.interval);
        },
        reset() {
            this.pause();
            this.remaining = this.total * 60;
        },
    }));
</script>
@endscript
