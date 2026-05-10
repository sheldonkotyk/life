<div class="space-y-6">
    <div>
        <flux:heading size="lg">Family tree</flux:heading>
        <flux:text size="sm" variant="subtle">
            Generations are inferred from parent-of relationships. Add father/mother connections to shape the tree.
        </flux:text>
    </div>

    @if ($members->isEmpty())
        <flux:callout color="zinc" icon="users">
            Add family members to see them in the tree.
        </flux:callout>
    @else
        <div class="overflow-x-auto">
            <div class="inline-flex flex-col items-center gap-10 min-w-full py-4">
                @foreach ($rows as $level => $row)
                    <div class="flex flex-wrap items-start justify-center gap-4">
                        @foreach ($row as $m)
                            @php
                                $parentIds = $parentsOf[$m->id] ?? [];
                                $childIds = $childrenOf[$m->id] ?? [];
                                $isImmediate = isset($immediateIds[$m->id]);
                                $guests = $guestsOf[$m->id] ?? [];
                            @endphp
                            <div class="flex items-start gap-2">
                                <div class="flex flex-col items-center gap-1 w-32">
                                    <x-avatar
                                        :member="$m"
                                        size="xl"
                                        @class([
                                            'ring-2',
                                            'ring-white dark:ring-zinc-900' => ! $isImmediate,
                                            'ring-offset-2 ring-offset-white dark:ring-offset-zinc-900 ring-zinc-900 dark:ring-white' => $isImmediate,
                                        ])
                                    />
                                    <div class="text-sm font-medium text-center truncate w-full">{{ $m->name }}</div>
                                    @if (count($parentIds) > 0)
                                        <div class="text-[10px] uppercase tracking-wide text-zinc-500">
                                            child of
                                            @foreach ($parentIds as $i => $pid)
                                                @if ($members->has($pid))
                                                    {{ $members[$pid]->name }}@if ($i < count($parentIds) - 1), @endif
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                    @if (count($childIds) > 0)
                                        <div class="text-[10px] uppercase tracking-wide text-zinc-500">
                                            {{ count($childIds) }} {{ \Illuminate\Support\Str::plural('child', count($childIds)) }}
                                        </div>
                                    @endif
                                </div>

                                @foreach ($guests as $g)
                                    <div class="flex items-center gap-1 pt-5">
                                        <div class="h-px w-3 bg-zinc-300 dark:bg-zinc-600"></div>
                                        <div class="flex flex-col items-center gap-1 w-20">
                                            <x-avatar :member="$g" size="lg" class="ring-2 ring-white dark:ring-zinc-900" />
                                            <div class="text-xs font-medium text-center truncate w-full">{{ $g->name }}</div>
                                            <div class="text-[9px] uppercase tracking-wide text-zinc-500">guest</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    @if (! $loop->last)
                        <div class="h-px w-24 bg-zinc-200 dark:bg-zinc-700"></div>
                    @endif
                @endforeach
            </div>
        </div>

    @endif
</div>
