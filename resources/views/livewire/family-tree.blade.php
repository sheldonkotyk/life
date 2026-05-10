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
                            @endphp
                            <div class="flex flex-col items-center gap-1 w-32">
                                <div
                                    class="size-16 rounded-full ring-2 ring-white dark:ring-zinc-900 shadow-sm flex items-center justify-center text-white font-semibold text-lg"
                                    style="background-color: {{ $m->color }}"
                                    title="{{ $m->name }}"
                                >
                                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($m->name, 0, 1)) }}
                                </div>
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
                        @endforeach
                    </div>
                    @if (! $loop->last)
                        <div class="h-px w-24 bg-zinc-200 dark:bg-zinc-700"></div>
                    @endif
                @endforeach
            </div>
        </div>

        @if (! empty($partnerPairs))
            <flux:card>
                <flux:heading size="sm">Partners</flux:heading>
                <ul class="mt-2 space-y-1">
                    @foreach ($partnerPairs as [$aId, $bId])
                        @if ($members->has($aId) && $members->has($bId))
                            <li class="text-sm flex items-center gap-2">
                                <span class="inline-block size-2 rounded-full" style="background-color: {{ $members[$aId]->color }}"></span>
                                <span class="font-medium">{{ $members[$aId]->name }}</span>
                                <flux:text size="sm" variant="subtle">&amp;</flux:text>
                                <span class="inline-block size-2 rounded-full" style="background-color: {{ $members[$bId]->color }}"></span>
                                <span class="font-medium">{{ $members[$bId]->name }}</span>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </flux:card>
        @endif
    @endif
</div>
