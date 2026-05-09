<div class="max-w-xl mx-auto py-8 space-y-6">
    <flux:heading size="xl">Profile</flux:heading>

    <flux:tab.group>
        <flux:tabs wire:model.live="tab">
            <flux:tab name="profile">Profile</flux:tab>
            <flux:tab name="defaults">Defaults</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="profile" class="space-y-6">
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

    <flux:card>
        <flux:heading size="lg">Join another household</flux:heading>
        <flux:text size="sm" variant="subtle" class="mb-3">
            Enter an invite code to switch to a different household.
        </flux:text>
        <form wire:submit="joinHousehold" class="flex items-start gap-2">
            <flux:input
                wire:model="joinCode"
                placeholder="INVITE CODE"
                class="font-mono uppercase"
                maxlength="12"
            />
            <flux:button type="submit" variant="primary">Join</flux:button>
        </form>
    </flux:card>
        </flux:tab.panel>

        <flux:tab.panel name="defaults" class="space-y-6">
            <flux:card>
                <flux:heading size="lg">Default attendance</flux:heading>
                <flux:text size="sm" variant="subtle" class="mt-1 mb-4">
                    Meals you typically attend. Used as the starting point for meal planning.
                </flux:text>

                @php $member = $this->member; @endphp

                @if (! $member)
                    <flux:callout color="zinc" icon="user">
                        Your account isn't linked to a family member yet. Ask a household admin to link you, or add yourself on the Family page.
                    </flux:callout>
                @else
                    @php
                        $dayLabels = ['sun' => 'Sun', 'mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat'];
                        $slots = \App\Livewire\Profile::SLOTS;
                        $days = \App\Livewire\Profile::DAYS;
                    @endphp
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-700">
                                    <th class="text-left p-2 font-semibold text-zinc-600 dark:text-zinc-300 w-20">Day</th>
                                    @foreach ($slots as $slot)
                                        @php
                                            $allSlotIn = collect($days)->every(fn ($d) => $member->attendsByDefault($d, $slot));
                                        @endphp
                                        <th class="text-center p-2 font-semibold text-zinc-600 dark:text-zinc-300 capitalize">
                                            <div>{{ $slot }}</div>
                                            <flux:button size="xs" class="mt-1" variant="ghost"
                                                wire:click="setSlotAttendance('{{ $slot }}', {{ $allSlotIn ? 'false' : 'true' }})">
                                                {{ $allSlotIn ? 'Skip all' : 'All in' }}
                                            </flux:button>
                                        </th>
                                    @endforeach
                                    <th class="text-right p-2 font-semibold text-zinc-500 w-20">All day</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($dayLabels as $dayKey => $dayLabel)
                                    @php
                                        $allIn = collect($slots)->every(fn ($s) => $member->attendsByDefault($dayKey, $s));
                                    @endphp
                                    <tr class="border-b border-zinc-100 dark:border-zinc-800 last:border-b-0">
                                        <td class="p-2 font-semibold">{{ $dayLabel }}</td>
                                        @foreach ($slots as $slot)
                                            @php $attending = $member->attendsByDefault($dayKey, $slot); @endphp
                                            <td class="p-2 text-center">
                                                <label class="inline-flex items-center justify-center cursor-pointer">
                                                    <flux:checkbox
                                                        wire:key="default-{{ $member->id }}-{{ $dayKey }}-{{ $slot }}-{{ $attending ? '1' : '0' }}"
                                                        :checked="$attending"
                                                        wire:click="toggleAttendance('{{ $dayKey }}', '{{ $slot }}')"
                                                    />
                                                </label>
                                            </td>
                                        @endforeach
                                        <td class="p-2 text-right">
                                            <flux:button size="xs" variant="ghost"
                                                wire:click="setDayAttendance('{{ $dayKey }}', {{ $allIn ? 'false' : 'true' }})">
                                                {{ $allIn ? 'Skip' : 'All in' }}
                                            </flux:button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </flux:card>
        </flux:tab.panel>
    </flux:tab.group>
</div>
