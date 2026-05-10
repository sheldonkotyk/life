<div class="space-y-6">
    <div>
        <flux:heading size="lg">Connections</flux:heading>
        <flux:text size="sm" variant="subtle">
            Map relationships between family members and guests — parents, siblings, partners, friends.
        </flux:text>
    </div>

    @if ($members->count() < 2)
        <flux:callout color="zinc" icon="users">
            Add at least two family members or guests to start linking them.
        </flux:callout>
    @else
        <flux:modal name="connection-form" class="md:w-[32rem]">
            <form wire:submit="add" class="space-y-4">
                <div>
                    <flux:heading size="lg">Add a connection</flux:heading>
                    <flux:text size="sm" variant="subtle">Pick two people and how they're related.</flux:text>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <flux:field>
                        <flux:label>Person</flux:label>
                        <flux:select wire:model="fromId">
                            @foreach ($members as $m)
                                <flux:select.option value="{{ $m->id }}">
                                    {{ $m->name }}{{ $m->is_guest ? ' (guest)' : '' }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label>Is the</flux:label>
                        <flux:select wire:model="type">
                            @foreach ($types as $key => $info)
                                <flux:select.option value="{{ $key }}">{{ $info['label'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label>Of</flux:label>
                        <flux:select wire:model="toId">
                            @foreach ($members as $m)
                                <flux:select.option value="{{ $m->id }}">
                                    {{ $m->name }}{{ $m->is_guest ? ' (guest)' : '' }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="toId" />
                        <flux:error name="fromId" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Notes</flux:label>
                        <flux:input wire:model="notes" placeholder="optional" />
                    </flux:field>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" icon="plus">Connect</flux:button>
                </div>
            </form>
        </flux:modal>

        @if ($reciprocalFromId && $reciprocalToId)
            @php
                $recipFrom = $members->firstWhere('id', $reciprocalFromId);
                $recipTo = $members->firstWhere('id', $reciprocalToId);
            @endphp
            @if ($recipFrom && $recipTo)
                <flux:card>
                    <flux:heading size="sm">Add the reciprocal?</flux:heading>
                    <div class="flex flex-wrap items-center gap-2 mt-2">
                        <flux:text size="sm" variant="subtle">Should we also record that</flux:text>
                        <strong>{{ $recipFrom->name }}</strong>
                        <flux:text size="sm" variant="subtle">is the</flux:text>
                        @if (count($reciprocalOptions) === 1)
                            <strong>{{ \Illuminate\Support\Str::of($types[$reciprocalOptions[0]]['label'])->replaceLast(' of', '') }}</strong>
                        @else
                            <flux:select wire:model="reciprocalType" size="sm" class="!w-auto">
                                @foreach ($reciprocalOptions as $opt)
                                    <flux:select.option value="{{ $opt }}">{{ \Illuminate\Support\Str::of($types[$opt]['label'])->replaceLast(' of', '') }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @endif
                        <flux:text size="sm" variant="subtle">of</flux:text>
                        <strong>{{ $recipTo->name }}</strong>
                        <flux:text size="sm" variant="subtle">?</flux:text>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 mt-3">
                        <flux:button type="button" variant="primary" icon="plus" wire:click="confirmReciprocal">
                            Yes, add it
                        </flux:button>
                        <flux:button type="button" variant="ghost" wire:click="dismissReciprocal">
                            No thanks
                        </flux:button>
                    </div>
                </flux:card>
            @endif
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <flux:button.group>
                    <flux:button size="sm" icon="share" variant="{{ $view === 'tree' ? 'primary' : 'ghost' }}" wire:click="setView('tree')">Tree</flux:button>
                    <flux:button size="sm" icon="list-bullet" variant="{{ $view === 'list' ? 'primary' : 'ghost' }}" wire:click="setView('list')">List</flux:button>
                </flux:button.group>
                <flux:modal.trigger name="connection-form">
                    <flux:button size="sm" variant="primary" icon="plus">Add connection</flux:button>
                </flux:modal.trigger>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:text size="sm" variant="subtle">Filter:</flux:text>
                <flux:button size="sm" variant="{{ $focusMemberId === null ? 'primary' : 'ghost' }}" wire:click="focus(null)">Everyone</flux:button>
                @foreach ($members as $m)
                    <flux:button size="sm" variant="{{ $focusMemberId === $m->id ? 'primary' : 'ghost' }}" wire:click="focus({{ $m->id }})">
                        <x-avatar :member="$m" size="xs" class="mr-1" />
                        {{ $m->name }}
                    </flux:button>
                @endforeach
            </div>
        </div>

        @if ($view === 'tree')
            <livewire:family-tree :focus-member-id="$focusMemberId" :key="'family-tree-'.($focusMemberId ?? 'all')" />
        @else
        <flux:card>
            @if ($pairs->isEmpty())
                <flux:text variant="subtle">
                    No connections yet. Use "Add connection" to map out who's related to whom.
                </flux:text>
            @else
                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($pairs as $c)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="flex items-center gap-2 min-w-0">
                                <x-avatar :member="$c->fromMember" size="sm" />
                                <span class="font-medium">{{ $c->fromMember->name }}</span>
                                @if ($c->fromMember->is_guest)
                                    <flux:badge size="sm" color="zinc">guest</flux:badge>
                                @endif
                                <flux:text size="sm" variant="subtle" class="mx-1">
                                    {{ \App\Models\FamilyConnection::TYPES[$c->type]['label'] ?? $c->type }}
                                </flux:text>
                                <x-avatar :member="$c->toMember" size="sm" />
                                <span class="font-medium">{{ $c->toMember->name }}</span>
                                @if ($c->toMember->is_guest)
                                    <flux:badge size="sm" color="zinc">guest</flux:badge>
                                @endif
                                @if ($c->notes)
                                    <flux:text size="sm" variant="subtle" class="ml-2 truncate">— {{ $c->notes }}</flux:text>
                                @endif
                            </div>
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="trash"
                                wire:click="remove({{ $c->id }})"
                                wire:confirm="Remove this connection?"
                            />
                        </li>
                    @endforeach
                </ul>
            @endif
        </flux:card>
        @endif
    @endif
</div>
