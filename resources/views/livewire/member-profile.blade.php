<div class="space-y-6">
    <div>
        <flux:button icon="arrow-left" variant="ghost" size="sm" :href="route('household')" wire:navigate>Back</flux:button>
    </div>

    <div class="flex items-center gap-4">
        <x-avatar :member="$member" size="xl" />
        <div>
            <flux:heading size="xl">{{ $member->name }}</flux:heading>
            <flux:text size="sm" variant="subtle">
                {{ $member->is_guest ? 'Guest' : ($member->is_child ? 'Child' : 'Adult') }}
            </flux:text>
        </div>
    </div>

    @if (session('status'))
        <flux:callout icon="check-circle" color="green">{{ session('status') }}</flux:callout>
    @endif

    <flux:tab.group>
        <flux:tabs wire:model.live="tab">
            <flux:tab name="profile">Profile</flux:tab>
            @if ($this->canManageAvatar)
                <flux:tab name="avatar">Avatar builder</flux:tab>
            @endif
            <flux:tab name="defaults">Defaults</flux:tab>
            <flux:tab name="food">Food preferences</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="profile" class="space-y-6">
            @if ($this->canEditChildUser)
                <flux:callout color="indigo" icon="user">
                    {{ $member->name }} has a linked account. Changes to name, birthday, and timezone will update their account too.
                </flux:callout>
            @endif

            <flux:card>
                <form wire:submit="save" class="space-y-4">
                    <flux:input wire:model="name" label="Name" required />

                    <flux:input type="date" wire:model="birthday" label="Birthday" />

                    @if ($this->canEditChildUser)
                        <flux:select wire:model="timezone" label="Timezone" required>
                            @foreach ($timezones as $tz)
                                <flux:select.option value="{{ $tz }}">{{ $tz }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif

                    <flux:field>
                        <flux:label>Color</flux:label>
                        <flux:input type="color" wire:model="color" class:input="h-10!" />
                    </flux:field>

                    <div class="flex items-center gap-4">
                        <flux:checkbox wire:model="isChild" id="isChild" label="Child" />
                        <flux:checkbox wire:model="isGuest" id="isGuest" label="Guest" />
                    </div>

                    <flux:input wire:model="notes" label="Notes" placeholder="e.g. picky eater, vegetarian on Fridays" />

                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary">Save</flux:button>
                    </div>
                </form>
            </flux:card>

            @unless ($member->user_id)
                <flux:card>
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <flux:heading size="lg">Remove</flux:heading>
                            <flux:text size="sm" variant="subtle">Permanently remove this member from the household.</flux:text>
                        </div>
                        <flux:button variant="danger" wire:click="delete" wire:confirm="Remove {{ $member->name }}?">Remove</flux:button>
                    </div>
                </flux:card>
            @endunless
        </flux:tab.panel>

        @if ($this->canManageAvatar)
            <flux:tab.panel name="avatar" class="space-y-6">
                <flux:card>
                    <flux:heading size="lg">Build {{ $member->name }}'s avatar</flux:heading>
                    <flux:text size="sm" variant="subtle" class="mt-1 mb-4">
                        Mix and match shoes, clothes, hair, face, and headwear.
                    </flux:text>
                    <livewire:avatar-builder :member="$member" :key="'avatar-builder-'.$member->id" />
                </flux:card>
            </flux:tab.panel>
        @endif

        <flux:tab.panel name="defaults" class="space-y-6">
            <flux:card>
                <flux:heading size="lg">Default attendance</flux:heading>
                <flux:text size="sm" variant="subtle" class="mt-1 mb-4">
                    Meals {{ $member->name }} typically attends. Used as the starting point for meal planning.
                </flux:text>

                @php
                    $dayLabels = ['sun' => 'Sun', 'mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat'];
                    $slots = \App\Livewire\MemberProfile::SLOTS;
                    $days = \App\Livewire\MemberProfile::DAYS;
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
            </flux:card>
        </flux:tab.panel>

        <flux:tab.panel name="food" class="space-y-6">
            <flux:card>
                <flux:heading size="lg">Food preferences</flux:heading>

                <div class="mt-4 space-y-4">
                    @foreach (['like' => '👍 Likes', 'dislike' => '👎 Dislikes', 'allergy' => '⚠️ Allergies'] as $type => $label)
                        @php $items = $preferences->where('type', $type); @endphp
                        <div>
                            <flux:text size="xs" class="uppercase tracking-wide text-zinc-400">{{ $label }}</flux:text>
                            @if ($items->isNotEmpty())
                                <div class="flex flex-wrap gap-1 mt-1">
                                    @foreach ($items as $p)
                                        <flux:badge size="sm" color="{{ $type === 'allergy' ? 'red' : 'zinc' }}">
                                            {{ $p->food }}
                                            <flux:badge.close wire:click="removePreference({{ $p->id }})" />
                                        </flux:badge>
                                    @endforeach
                                </div>
                            @else
                                <flux:text size="sm" variant="subtle">None.</flux:text>
                            @endif
                        </div>
                    @endforeach

                    @if ($addingPreference)
                        <div class="p-3 bg-zinc-50 dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 grid grid-cols-1 sm:grid-cols-5 gap-2">
                            <div class="sm:col-span-2">
                                <flux:input wire:model="prefFood" placeholder="Food" size="sm" />
                            </div>
                            <flux:select wire:model="prefType" size="sm">
                                <flux:select.option value="like">Like</flux:select.option>
                                <flux:select.option value="dislike">Dislike</flux:select.option>
                                <flux:select.option value="allergy">Allergy</flux:select.option>
                            </flux:select>
                            <flux:input wire:model="prefNotes" placeholder="Notes (optional)" size="sm" />
                            <flux:button size="sm" variant="primary" wire:click="addPreference">Add</flux:button>
                        </div>
                    @else
                        <flux:button size="sm" variant="ghost" icon="plus" wire:click="startAddingPreference">Add food preference</flux:button>
                    @endif
                </div>
            </flux:card>
        </flux:tab.panel>
    </flux:tab.group>
</div>
