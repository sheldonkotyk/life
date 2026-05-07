<div class="max-w-xl mx-auto py-8">
    <flux:heading size="xl" class="mb-6">Profile</flux:heading>

    <flux:card>
        <form wire:submit="save" class="space-y-4">
            <div class="flex items-center gap-4">
                <div class="relative w-20 h-20">
                    <img src="{{ auth()->user()->avatar }}" alt="Avatar"
                        class="w-20 h-20 rounded-full object-cover ring-1 ring-zinc-200"
                        wire:loading.class="opacity-40" wire:target="avatar" />
                    <div wire:loading wire:target="avatar"
                        class="absolute inset-0 flex items-center justify-center">
                        <flux:icon.loading class="size-6 text-zinc-500" />
                    </div>
                </div>
                <div class="flex-1 flex flex-col gap-2">
                    <flux:file-upload wire:model="avatar" accept="image/*" wire:loading.attr="disabled" wire:target="avatar">
                        <flux:file-upload.dropzone
                            inline
                            with-progress
                            heading="Upload new photo"
                            text="PNG or JPG, up to 1 MB"
                        />
                    </flux:file-upload>
                    <flux:error name="avatar" />
                    @if (auth()->user()->getRawOriginal('avatar') && ! str_starts_with(auth()->user()->getRawOriginal('avatar'), 'http'))
                        <flux:button type="button" size="xs" variant="ghost" wire:click="removeAvatar"
                            wire:confirm="Remove your custom avatar?">
                            Remove custom avatar
                        </flux:button>
                    @endif
                </div>
            </div>

            <flux:input wire:model="name" label="Name" required />

            <flux:input type="date" wire:model="birthday" label="Birthday" />

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
