<div class="space-y-6">
    <div class="flex items-baseline justify-between">
        <flux:heading size="xl">Family</flux:heading>
        <div class="flex items-center gap-3">
            <flux:text variant="subtle">{{ $members->count() }} {{ Str::plural('member', $members->count()) }}</flux:text>
            <flux:button variant="primary" icon="plus" wire:click="create">Add family member</flux:button>
        </div>
    </div>

    {{-- Add / edit modal --}}
    <flux:modal name="member-form" @close="resetForm" class="md:w-[40rem]">
        <flux:heading size="lg">{{ $editingId ? 'Edit member' : 'Add family member' }}</flux:heading>

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

            @if ($editingId)
                <div class="sm:col-span-6">
                    <flux:subheading>Allergies</flux:subheading>
                    <div class="flex flex-wrap items-center gap-2 mt-2">
                        @foreach ($this->editingAllergies as $allergy)
                            <flux:badge color="red">
                                {{ $allergy->food }}
                                <flux:badge.close wire:click="removePreference({{ $allergy->id }})" />
                            </flux:badge>
                        @endforeach
                        @if ($this->editingAllergies->isEmpty())
                            <flux:text size="sm" variant="subtle">No allergies added.</flux:text>
                        @endif
                    </div>
                    <div class="flex gap-2 mt-2">
                        <flux:input
                            wire:model="newAllergy"
                            wire:keydown.enter.prevent="addAllergy"
                            placeholder="e.g. peanuts"
                            size="sm"
                        />
                        <flux:button type="button" size="sm" variant="ghost" wire:click="addAllergy">Add</flux:button>
                    </div>
                </div>
            @endif

            <div class="sm:col-span-6">
                <flux:subheading>Daily macro targets <span class="text-zinc-400 font-normal">(optional)</span></flux:subheading>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-2">
                    <flux:field>
                        <flux:label>Calories</flux:label>
                        <flux:input type="number" step="1" min="0" wire:model="targetCalories" placeholder="2000" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Protein (g)</flux:label>
                        <flux:input type="number" step="0.1" min="0" wire:model="targetProteinG" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Carbs (g)</flux:label>
                        <flux:input type="number" step="0.1" min="0" wire:model="targetCarbsG" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Fat (g)</flux:label>
                        <flux:input type="number" step="0.1" min="0" wire:model="targetFatG" />
                    </flux:field>
                </div>
            </div>

            <div class="sm:col-span-6 flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">
                    {{ $editingId ? 'Update' : 'Add' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Member cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach ($members as $m)
            <flux:card>
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <x-avatar :member="$m" size="lg" />
                        <div>
                            <flux:heading size="lg">{{ $m->name }}</flux:heading>
                            <flux:text size="sm" variant="subtle">
                                {{ $m->is_child ? 'Child' : 'Adult' }}
                                @if ($m->user) · has account @endif
                                @if ($m->notes) · {{ $m->notes }} @endif
                            </flux:text>
                        </div>
                    </div>
                    <div class="flex gap-1">
                        <flux:button size="sm" variant="ghost" wire:click="edit({{ $m->id }})">Edit</flux:button>
                        <flux:button size="sm" variant="danger" wire:click="delete({{ $m->id }})" wire:confirm="Remove {{ $m->name }}?">Remove</flux:button>
                    </div>
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
                        <div class="flex gap-1">
                            <flux:button size="sm" variant="ghost" wire:click="edit({{ $m->id }})">Edit</flux:button>
                            <flux:button size="sm" variant="danger" wire:click="delete({{ $m->id }})" wire:confirm="Remove {{ $m->name }}?">Remove</flux:button>
                        </div>
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
