<div class="space-y-6">
    <div class="flex flex-wrap gap-3 items-baseline justify-between">
        <flux:heading size="xl">Shopping List</flux:heading>
        <div class="flex items-center gap-2">
            <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="shiftWeek(-1)">Prev week</flux:button>
            <flux:button size="sm" variant="ghost" icon-trailing="chevron-right" wire:click="shiftWeek(1)">Next week</flux:button>
            <flux:text size="sm" variant="subtle" class="ml-2 whitespace-nowrap">{{ $start->format('M j') }} – {{ $start->addDays(6)->format('M j') }}</flux:text>
        </div>
    </div>

    <flux:text size="sm" variant="subtle">
        Quantities are scaled to actual attendees (recipe servings ÷ recipe yield × eaters). Non-numeric quantities listed as-is.
    </flux:text>

    @forelse ($grouped as $category => $items)
        <flux:card>
            <flux:subheading class="mb-2">{{ $category }}</flux:subheading>
            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($items as $item)
                    <li class="py-2 flex items-baseline justify-between gap-3">
                        <div>
                            <span class="font-medium text-sm">{{ $item['name'] }}</span>
                            <flux:text size="xs" variant="subtle" class="ms-2">{{ implode(', ', array_unique($item['meals'])) }}</flux:text>
                        </div>
                        <div class="text-sm whitespace-nowrap">
                            @if ($item['qty_total'] > 0)
                                {{ rtrim(rtrim(number_format($item['qty_total'], 2), '0'), '.') }}
                                @if ($item['unit']) {{ $item['unit'] }} @endif
                            @endif
                            @if (! empty($item['qty_text']))
                                <flux:text size="sm" variant="subtle" class="inline">{{ implode(' + ', array_unique($item['qty_text'])) }}@if ($item['unit'])  {{ $item['unit'] }}@endif</flux:text>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </flux:card>
    @empty
        <flux:card class="text-center py-12">
            <flux:text variant="subtle">No meals planned for this week yet, or none have ingredients listed.</flux:text>
        </flux:card>
    @endforelse
</div>
