<flux:card>
    <flux:heading size="lg">Default meal times</flux:heading>
    <flux:text size="sm" variant="subtle" class="mb-3">
        When each meal usually happens. Times are interpreted in your timezone ({{ auth()->user()->getTimezone() }}). Individual meals can override these.
    </flux:text>

    <form wire:submit="save" class="space-y-3">
        @foreach ([
            ['label' => 'Breakfast', 'start' => 'breakfastStart', 'end' => 'breakfastEnd'],
            ['label' => 'Lunch', 'start' => 'lunchStart', 'end' => 'lunchEnd'],
            ['label' => 'Dinner', 'start' => 'dinnerStart', 'end' => 'dinnerEnd'],
        ] as $row)
            <div class="grid grid-cols-1 sm:grid-cols-[8rem_1fr_1fr] gap-2 items-end">
                <flux:text class="font-semibold">{{ $row['label'] }}</flux:text>
                <flux:input type="time" wire:model="{{ $row['start'] }}" label="Start" :readonly="! $this->canManage" />
                <flux:input type="time" wire:model="{{ $row['end'] }}" label="End" :readonly="! $this->canManage" />
            </div>
        @endforeach

        @error('breakfastEnd') <flux:text size="sm" class="text-red-600">{{ $message }}</flux:text> @enderror
        @error('lunchEnd') <flux:text size="sm" class="text-red-600">{{ $message }}</flux:text> @enderror
        @error('dinnerEnd') <flux:text size="sm" class="text-red-600">{{ $message }}</flux:text> @enderror

        @if ($this->canManage)
            <flux:button type="submit" variant="primary">Save meal times</flux:button>
        @endif
    </form>
</flux:card>
