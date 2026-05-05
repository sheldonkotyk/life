<div class="max-w-xl mx-auto py-8 space-y-6">
    <flux:heading size="xl">Household</flux:heading>

    @if (! $this->canManage)
        <flux:callout color="zinc" icon="lock-closed">
            Only administrators can edit this household.
        </flux:callout>
    @endif

    <flux:card>
        <form wire:submit="save" class="space-y-4">
            <flux:input wire:model="name" label="Household name" required :readonly="! $this->canManage" />

            @if ($this->canManage)
                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary">Save</flux:button>
                </div>
            @endif
        </form>
    </flux:card>

    <flux:card>
        <flux:heading size="lg">Invite code</flux:heading>
        <flux:text size="sm" variant="subtle" class="mb-3">
            Share this code so others can join your household.
        </flux:text>

        <div class="flex items-center gap-3">
            <flux:input readonly value="{{ $inviteCode }}" class="font-mono" />
            @if ($this->canManage)
                <flux:button
                    type="button"
                    variant="ghost"
                    wire:click="regenerateInviteCode"
                    wire:confirm="Regenerate invite code? The old one will stop working."
                >
                    Regenerate
                </flux:button>
            @endif
        </div>
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
