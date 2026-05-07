<div class="max-w-xl mx-auto py-8 space-y-6">
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
