<div class="space-y-6">
    <div class="flex flex-wrap items-baseline justify-between gap-3">
        <div>
            <flux:heading size="lg">Family</flux:heading>
            <flux:text size="sm" variant="subtle">
                Daily eaters. <flux:icon.user class="size-3 inline -mt-0.5 text-indigo-500" /> indicates a linked account.
            </flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">Add person</flux:button>
    </div>

    {{-- Add modal --}}
    <flux:modal name="member-form" @close="resetForm" class="md:w-[40rem]">
        <flux:heading size="lg">Add family member</flux:heading>

        <form wire:submit="save" class="grid grid-cols-1 sm:grid-cols-6 gap-4 mt-4 items-end">
            <div class="sm:col-span-4">
                <flux:field>
                    <flux:label>Name</flux:label>
                    <flux:input wire:model="name" placeholder="e.g. Alex" />
                    <flux:error name="name" />
                </flux:field>
            </div>

            <div class="sm:col-span-2">
                <flux:field>
                    <flux:label>Color</flux:label>
                    <flux:input type="color" wire:model="color" class:input="h-10!" />
                </flux:field>
            </div>

            <div class="sm:col-span-6 flex items-center gap-4 pt-6">
                <flux:checkbox wire:model="isChild" id="isChild" label="Child" />
                <flux:checkbox wire:model="isGuest" id="isGuest" label="Guest" />
            </div>

            <div class="sm:col-span-6">
                <flux:field>
                    <flux:label>Notes</flux:label>
                    <flux:input wire:model="notes" placeholder="e.g. picky eater, vegetarian on Fridays" />
                </flux:field>
            </div>

            <div class="sm:col-span-6 flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Add</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Member cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach ($members as $m)
            @php $isAdmin = $m->user && in_array($m->user->id, $adminIds, true); @endphp
            <flux:card>
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <x-avatar :member="$m" size="lg" />
                        <div>
                            <flux:heading size="lg" class="flex items-center gap-1.5">
                                {{ $m->name }}
                                @if ($m->user)
                                    <flux:tooltip content="Linked to {{ $m->user->email }}">
                                        <flux:icon.user class="size-4 text-indigo-500" />
                                    </flux:tooltip>
                                @endif
                                @if ($isAdmin)
                                    <flux:badge color="indigo" size="sm">Admin</flux:badge>
                                @endif
                            </flux:heading>
                            <flux:text size="sm" variant="subtle" class="whitespace-nowrap">
                                {{ $m->is_child ? 'Child' : 'Adult' }}@if ($m->notes) · {{ $m->notes }} @endif
                            </flux:text>
                        </div>
                    </div>
                    <flux:dropdown align="end">
                        <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />
                        <flux:menu>
                            <flux:menu.item icon="pencil-square" :href="route('member.profile', $m)" wire:navigate>Edit</flux:menu.item>
                            @if ($m->user && $canManage)
                                @if ($isAdmin)
                                    <flux:menu.item
                                        icon="shield-check"
                                        wire:click="removeAdmin({{ $m->user->id }})"
                                        wire:confirm="Remove admin from {{ $m->name }}?"
                                    >
                                        Remove admin
                                    </flux:menu.item>
                                @else
                                    <flux:menu.item
                                        icon="shield-check"
                                        wire:click="makeAdmin({{ $m->user->id }})"
                                    >
                                        Make admin
                                    </flux:menu.item>
                                @endif
                            @endif
                            @unless ($m->user)
                                <flux:menu.item
                                    icon="trash"
                                    variant="danger"
                                    wire:click="delete({{ $m->id }})"
                                    wire:confirm="Remove {{ $m->name }}?"
                                >
                                    Remove
                                </flux:menu.item>
                            @endunless
                        </flux:menu>
                    </flux:dropdown>
                </div>

                <div class="mt-4 space-y-3">
                    @foreach (['like' => '👍 Likes', 'dislike' => '👎 Dislikes', 'allergy' => '⚠️ Allergies'] as $type => $label)
                        @php $items = $m->preferences->where('type', $type); @endphp
                        @if ($items->isNotEmpty())
                            <div>
                                <flux:text size="xs" class="uppercase tracking-wide text-zinc-400">{{ $label }}</flux:text>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    @foreach ($items as $p)
                                        <flux:badge size="sm" color="zinc">
                                            {{ $p->food }}
                                            <button wire:click="removePreference({{ $p->id }})" class="ms-1 text-zinc-400 hover:text-red-500" title="Remove">×</button>
                                        </flux:badge>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach

                    @if ($prefMemberId === $m->id)
                        <div class="mt-3 p-3 bg-zinc-50 dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 grid grid-cols-1 sm:grid-cols-5 gap-2">
                            <div class="sm:col-span-2">
                                <flux:input wire:model="prefFood" placeholder="Food" size="sm" />
                            </div>
                            <flux:select wire:model="prefType" size="sm">
                                <flux:select.option value="like">Like</flux:select.option>
                                <flux:select.option value="dislike">Dislike</flux:select.option>
                                <flux:select.option value="allergy">Allergy</flux:select.option>
                            </flux:select>
                            <flux:input wire:model="prefNotes" placeholder="Notes (optional)" size="sm" />
                            <flux:button size="sm" variant="primary" wire:click="addPreference({{ $m->id }})">Add</flux:button>
                        </div>
                    @else
                        <flux:button size="xs" variant="ghost" wire:click="startAddingPreference({{ $m->id }})">+ Add food preference</flux:button>
                    @endif
                </div>
            </flux:card>
        @endforeach
    </div>

    @if ($guests->isNotEmpty())
        <flux:heading size="lg" class="mt-8">Guests</flux:heading>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach ($guests as $m)
                <flux:card>
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <x-avatar :member="$m" size="lg" />
                            <div>
                                <flux:heading size="lg">{{ $m->name }}</flux:heading>
                                @if ($m->notes)
                                    <flux:text size="sm" variant="subtle">{{ $m->notes }}</flux:text>
                                @endif
                            </div>
                        </div>
                        <flux:dropdown align="end">
                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" :href="route('member.profile', $m)" wire:navigate>Edit</flux:menu.item>
                                <flux:menu.item
                                    icon="trash"
                                    variant="danger"
                                    wire:click="delete({{ $m->id }})"
                                    wire:confirm="Remove {{ $m->name }}?"
                                >
                                    Remove
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>

                    <div class="mt-4 space-y-3">
                        @foreach (['like' => '👍 Likes', 'dislike' => '👎 Dislikes', 'allergy' => '⚠️ Allergies'] as $type => $label)
                            @php $items = $m->preferences->where('type', $type); @endphp
                            @if ($items->isNotEmpty())
                                <div>
                                    <flux:text size="xs" class="uppercase tracking-wide text-zinc-400">{{ $label }}</flux:text>
                                    <div class="flex flex-wrap gap-1 mt-1">
                                        @foreach ($items as $p)
                                            <flux:badge size="sm" color="zinc">
                                                {{ $p->food }}
                                                <button wire:click="removePreference({{ $p->id }})" class="ms-1 text-zinc-400 hover:text-red-500" title="Remove">×</button>
                                            </flux:badge>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach

                        @if ($prefMemberId === $m->id)
                            <div class="mt-3 p-3 bg-zinc-50 dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 grid grid-cols-1 sm:grid-cols-5 gap-2">
                                <div class="sm:col-span-2">
                                    <flux:input wire:model="prefFood" placeholder="Food" size="sm" />
                                </div>
                                <flux:select wire:model="prefType" size="sm">
                                    <flux:select.option value="like">Like</flux:select.option>
                                    <flux:select.option value="dislike">Dislike</flux:select.option>
                                    <flux:select.option value="allergy">Allergy</flux:select.option>
                                </flux:select>
                                <flux:input wire:model="prefNotes" placeholder="Notes (optional)" size="sm" />
                                <flux:button size="sm" variant="primary" wire:click="addPreference({{ $m->id }})">Add</flux:button>
                            </div>
                        @else
                            <flux:button size="xs" variant="ghost" wire:click="startAddingPreference({{ $m->id }})">+ Add food preference</flux:button>
                        @endif
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif
</div>
