<div class="max-w-4xl mx-auto py-8 space-y-6">
    <flux:heading size="xl">Household</flux:heading>

    @if (! $this->canManage)
        <flux:callout color="zinc" icon="lock-closed">
            Only administrators can edit this household.
        </flux:callout>
    @endif

    <flux:card>
        <form wire:submit="save" class="flex items-start gap-2">
            <flux:input wire:model="name" placeholder="Household name" required :readonly="! $this->canManage" />
            @if ($this->canManage)
                <flux:button type="submit" variant="primary">Save</flux:button>
            @endif
        </form>
    </flux:card>

    @php $inviteUrl = route('login.invite.link', ['code' => $inviteCode]); @endphp
    <flux:card x-data="{
        copied: null,
        copy(text, key) {
            navigator.clipboard.writeText(text).then(() => {
                this.copied = key;
                setTimeout(() => { if (this.copied === key) this.copied = null; }, 1500);
            });
        }
    }">
        <flux:heading size="lg">Invite</flux:heading>
        <flux:text size="sm" variant="subtle" class="mb-3">
            Share the link or code so others can join your household.
        </flux:text>

        <div class="space-y-3">
            <div class="flex items-center gap-2">
                <flux:input readonly value="{{ $inviteUrl }}" class="font-mono" />
                <flux:button type="button" variant="ghost" @click="copy('{{ $inviteUrl }}', 'link')">
                    <span x-text="copied === 'link' ? 'Copied!' : 'Copy link'"></span>
                </flux:button>
            </div>

            <div class="flex items-center gap-2">
                <flux:input readonly value="{{ $inviteCode }}" class="font-mono" />
                <flux:button type="button" variant="ghost" @click="copy('{{ $inviteCode }}', 'code')">
                    <span x-text="copied === 'code' ? 'Copied!' : 'Copy code'"></span>
                </flux:button>
                @if ($this->canManage)
                    <flux:button
                        type="button"
                        variant="ghost"
                        wire:click="regenerateInviteCode"
                        wire:confirm="Regenerate invite code? The old one and link will stop working."
                    >
                        Regenerate
                    </flux:button>
                @endif
            </div>
        </div>
    </flux:card>

    @php $isSoleMember = $members->count() === 1; @endphp
    <flux:card>
        <flux:heading size="lg">Leave household</flux:heading>
        <flux:text size="sm" variant="subtle" class="mb-3">
            @if ($isSoleMember)
                You're the only member. Leaving will permanently delete this household and all its data (recipes, meal plans, family members).
            @else
                You'll be removed from this household. You can rejoin later with the invite code.
            @endif
        </flux:text>

        @if ($isSoleMember)
            <flux:button
                type="button"
                variant="danger"
                wire:click="leaveAndDeleteHousehold"
                wire:confirm="Permanently delete this household and all its data? This cannot be undone."
            >
                Delete household
            </flux:button>
        @elseif ($choosingSuccessor)
            @php $candidates = $members->where('id', '!=', auth()->id()); @endphp
            <flux:text class="mb-2">You're the only admin. Choose who should take over before you leave:</flux:text>
            <flux:select wire:model="successorId" placeholder="Select a member" class="mb-3">
                @foreach ($candidates as $candidate)
                    <flux:select.option value="{{ $candidate->id }}">{{ $candidate->name }}</flux:select.option>
                @endforeach
            </flux:select>
            @error('successorId') <flux:text size="sm" class="text-red-600 mb-2">{{ $message }}</flux:text> @enderror
            <div class="flex gap-2">
                <flux:button
                    type="button"
                    variant="danger"
                    wire:click="confirmLeaveWithSuccessor"
                    wire:confirm="Promote selected member to admin and leave this household?"
                >
                    Promote and leave
                </flux:button>
                <flux:button type="button" variant="ghost" wire:click="cancelLeave">Cancel</flux:button>
            </div>
        @else
            <flux:button
                type="button"
                variant="danger"
                wire:click="leaveHousehold"
                wire:confirm="Leave this household?"
            >
                Leave household
            </flux:button>
        @endif
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

    @if ($attendanceMember)
        @php
            $dayLabels = ['sun' => 'Sun', 'mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat'];
            $slots = \App\Livewire\HouseholdSettings::SLOTS;
        @endphp
        <flux:card>
            <div class="flex flex-wrap gap-3 items-baseline justify-between mb-3">
                <div>
                    <flux:heading size="lg">Default weekly attendance</flux:heading>
                    <flux:text size="sm" variant="subtle">
                        Set the meals each person is typically there for. Used as the starting point for meal planning.
                    </flux:text>
                </div>

                @if ($attendanceMembers->count() > 1)
                    <flux:select wire:model.live="attendanceMemberId" class:input="w-full sm:w-56">
                        @foreach ($attendanceMembers as $m)
                            <flux:select.option value="{{ $m->id }}">
                                {{ $m->name }}@if ($m->is_guest) (guest)@endif
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-700">
                            <th class="text-left p-3 font-semibold text-zinc-600 dark:text-zinc-300 w-32">Day</th>
                            @foreach ($slots as $slot)
                                @php
                                    $allSlotIn = collect(\App\Livewire\HouseholdSettings::DAYS)
                                        ->every(fn ($d) => $attendanceMember->attendsByDefault($d, $slot));
                                @endphp
                                <th class="text-center p-3 font-semibold text-zinc-600 dark:text-zinc-300 capitalize">
                                    <div>{{ $slot }}</div>
                                    <flux:button size="xs" class="mt-1" variant="ghost"
                                        wire:click="setSlotAttendance('{{ $slot }}', {{ $allSlotIn ? 'false' : 'true' }})">
                                        {{ $allSlotIn ? 'Skip all' : 'All in' }}
                                    </flux:button>
                                </th>
                            @endforeach
                            <th class="text-right p-3 font-semibold text-zinc-500 w-24">All day</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dayLabels as $dayKey => $dayLabel)
                            @php
                                $allIn = collect($slots)->every(fn ($s) => $attendanceMember->attendsByDefault($dayKey, $s));
                            @endphp
                            <tr class="border-b border-zinc-100 dark:border-zinc-800 last:border-b-0">
                                <td class="p-3 font-semibold">{{ $dayLabel }}</td>
                                @foreach ($slots as $slot)
                                    @php $attending = $attendanceMember->attendsByDefault($dayKey, $slot); @endphp
                                    <td class="p-3 text-center">
                                        <label class="inline-flex items-center justify-center cursor-pointer">
                                            <flux:checkbox
                                                wire:key="default-{{ $attendanceMember->id }}-{{ $dayKey }}-{{ $slot }}-{{ $attending ? '1' : '0' }}"
                                                :checked="$attending"
                                                wire:click="toggleAttendance('{{ $dayKey }}', '{{ $slot }}')"
                                            />
                                        </label>
                                    </td>
                                @endforeach
                                <td class="p-3 text-right">
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
    @endif

    <flux:card>
        <flux:heading size="lg" class="mb-3">Members</flux:heading>
        <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @foreach ($members as $member)
                @php $isAdmin = $member->pivot->role === 'admin'; @endphp
                <li class="py-3 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="truncate">{{ $member->name }}</span>
                            @if ($isAdmin)
                                <flux:badge color="indigo" size="sm">Admin</flux:badge>
                            @endif
                        </div>
                        <flux:text size="xs" variant="subtle">{{ $member->email }}</flux:text>
                    </div>

                    @if ($this->canManage)
                        @if ($isAdmin)
                            <flux:button
                                size="sm"
                                variant="ghost"
                                wire:click="removeAdmin({{ $member->id }})"
                                wire:confirm="Remove admin from {{ $member->name }}?"
                            >
                                Remove admin
                            </flux:button>
                        @else
                            <flux:button
                                size="sm"
                                variant="ghost"
                                wire:click="makeAdmin({{ $member->id }})"
                            >
                                Make admin
                            </flux:button>
                        @endif
                    @endif
                </li>
            @endforeach
        </ul>
    </flux:card>
</div>
