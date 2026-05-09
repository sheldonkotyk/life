<div class="max-w-4xl mx-auto py-8 space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ $name ?: 'Household' }}</flux:heading>
            <flux:text size="sm" variant="subtle">
                {{ $members->count() }} {{ Str::plural('member', $members->count()) }}
            </flux:text>
        </div>
        <flux:modal.trigger name="invite-modal">
            <flux:button variant="primary" icon="user-plus">Invite</flux:button>
        </flux:modal.trigger>
    </div>

    @if (! $this->canManage)
        <flux:callout color="zinc" icon="lock-closed">
            Only administrators can edit this household.
        </flux:callout>
    @endif

    <flux:tab.group>
        <flux:tabs wire:model="tab">
            <flux:tab name="people">People</flux:tab>
            <flux:tab name="settings">Settings</flux:tab>
            <flux:tab name="danger">Leave / delete</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="people">
            <livewire:family />
        </flux:tab.panel>

        <flux:tab.panel name="settings" class="space-y-6">
            <flux:card>
                <flux:heading size="lg">Household name</flux:heading>
                <form wire:submit="save" class="flex items-start gap-2 mt-3">
                    <flux:input wire:model="name" placeholder="Household name" required :readonly="! $this->canManage" />
                    @if ($this->canManage)
                        <flux:button type="submit" variant="primary">Save</flux:button>
                    @endif
                </form>
            </flux:card>

            <livewire:household-meal-times />
        </flux:tab.panel>

        <flux:tab.panel name="danger">
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
        </flux:tab.panel>
    </flux:tab.group>

    @php $inviteUrl = route('login.invite.link', ['code' => $inviteCode]); @endphp
    <flux:modal name="invite-modal" class="md:w-96">
        <div x-data="{
            copied: null,
            copy(text, key) {
                navigator.clipboard.writeText(text).then(() => {
                    this.copied = key;
                    setTimeout(() => { if (this.copied === key) this.copied = null; }, 1500);
                });
            }
        }" class="space-y-4">
            <div>
                <flux:heading size="lg">Invite</flux:heading>
                <flux:text size="sm" variant="subtle">
                    Share the link or code so others can join your household.
                </flux:text>
            </div>

            <div class="space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                    <flux:input readonly value="{{ $inviteUrl }}" class="font-mono w-full sm:flex-1 sm:min-w-0" />
                    <flux:button type="button" variant="ghost" class="self-start sm:self-auto" @click="copy('{{ $inviteUrl }}', 'link')">
                        <span x-text="copied === 'link' ? 'Copied!' : 'Copy link'"></span>
                    </flux:button>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                    <flux:input readonly value="{{ $inviteCode }}" class="font-mono w-full sm:flex-1 sm:min-w-0" />
                    <div class="flex items-center gap-2">
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
            </div>
        </div>
    </flux:modal>
</div>
