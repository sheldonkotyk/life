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
        <flux:card>
            <flux:heading size="sm">Add a connection</flux:heading>
            <form wire:submit="add" class="grid grid-cols-1 sm:grid-cols-12 gap-3 mt-3 items-end">
                <div class="sm:col-span-3">
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
                </div>

                <div class="sm:col-span-3">
                    <flux:field>
                        <flux:label>Is the</flux:label>
                        <flux:select wire:model="type">
                            @foreach ($types as $key => $info)
                                <flux:select.option value="{{ $key }}">{{ $info['label'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                </div>

                <div class="sm:col-span-3">
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
                </div>

                <div class="sm:col-span-3">
                    <flux:field>
                        <flux:label>Notes</flux:label>
                        <flux:input wire:model="notes" placeholder="optional" />
                    </flux:field>
                </div>

                <div class="sm:col-span-12 flex justify-end">
                    <flux:button type="submit" variant="primary" icon="plus">Connect</flux:button>
                </div>
            </form>
        </flux:card>

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

        <div class="flex flex-wrap items-center gap-2">
            <flux:text size="sm" variant="subtle">Filter:</flux:text>
            <flux:button size="sm" variant="{{ $focusMemberId === null ? 'primary' : 'ghost' }}" wire:click="focus(null)">Everyone</flux:button>
            @foreach ($members as $m)
                <flux:button size="sm" variant="{{ $focusMemberId === $m->id ? 'primary' : 'ghost' }}" wire:click="focus({{ $m->id }})">
                    <span class="inline-block size-2 rounded-full mr-1" style="background-color: {{ $m->color }}"></span>
                    {{ $m->name }}
                </flux:button>
            @endforeach
        </div>

        <flux:card>
            @if ($pairs->isEmpty())
                <flux:text variant="subtle">
                    No connections yet. Add one above to map out who's related to whom.
                </flux:text>
            @else
                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($pairs as $c)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="inline-block size-3 rounded-full" style="background-color: {{ $c->fromMember->color }}"></span>
                                <span class="font-medium">{{ $c->fromMember->name }}</span>
                                @if ($c->fromMember->is_guest)
                                    <flux:badge size="sm" color="zinc">guest</flux:badge>
                                @endif
                                <flux:text size="sm" variant="subtle" class="mx-1">
                                    {{ \App\Models\FamilyConnection::TYPES[$c->type]['label'] ?? $c->type }}
                                </flux:text>
                                <span class="inline-block size-3 rounded-full" style="background-color: {{ $c->toMember->color }}"></span>
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
</div>
