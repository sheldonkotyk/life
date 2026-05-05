<div class="max-w-xl mx-auto py-8">
    <flux:heading size="xl" class="mb-6">Profile</flux:heading>

    <flux:card>
        <form wire:submit="save" class="space-y-4">
            <flux:input wire:model="name" label="Name" required />

            <flux:select wire:model="timezone" label="Timezone" required>
                @foreach ($timezones as $tz)
                    <flux:select.option value="{{ $tz }}">{{ $tz }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:text size="xs" variant="subtle">
                Detected from your browser on first sign-in. Override here if you'd like.
            </flux:text>

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </flux:card>
</div>
