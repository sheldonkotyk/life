@php
    $colors = \App\Livewire\Lists::LIST_COLORS;
    $colorClass = fn ($c) => match ($c) {
        'red' => 'bg-red-500',
        'orange' => 'bg-orange-500',
        'amber' => 'bg-amber-500',
        'lime' => 'bg-lime-500',
        'emerald' => 'bg-emerald-500',
        'sky' => 'bg-sky-500',
        'indigo' => 'bg-indigo-500',
        'violet' => 'bg-violet-500',
        'pink' => 'bg-pink-500',
        default => 'bg-zinc-400',
    };
@endphp
<div class="space-y-4">
    <div class="flex items-baseline justify-between">
        <flux:heading size="xl">Lists</flux:heading>
        @if ($selected)
            <flux:text size="sm" variant="subtle">{{ $selected->name }}</flux:text>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-4">
        {{-- ============ SIDEBAR ============ --}}
        <div class="space-y-3"
            x-data="{
                draggingListId: null,
                draggingItemId: null,
                dragOverList: null,
                onListDragStart(e, id) { this.draggingListId = id; e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('application/x-list-id', String(id)); },
                onListDragEnd() { this.draggingListId = null; this.dragOverList = null; },
                onListDragOver(e, id) {
                    if (this.draggingListId === null && this.draggingItemId === null) return;
                    e.preventDefault(); e.dataTransfer.dropEffect = 'move'; this.dragOverList = id;
                },
                onListDrop(e, id) {
                    e.preventDefault();
                    const itemId = e.dataTransfer.getData('application/x-item-id');
                    if (itemId) {
                        $wire.moveItemToList(parseInt(itemId), id);
                    } else if (this.draggingListId !== null) {
                        const ids = Array.from(this.$refs.listOl.querySelectorAll('[data-list-id]')).map(el => parseInt(el.dataset.listId));
                        const from = ids.indexOf(this.draggingListId);
                        const to = ids.indexOf(id);
                        if (from > -1 && to > -1 && from !== to) {
                            ids.splice(to, 0, ids.splice(from, 1)[0]);
                            $wire.reorderLists(ids);
                        }
                    }
                    this.draggingListId = null; this.dragOverList = null;
                },
            }"
        >
            <flux:card>
                <flux:subheading class="mb-2">Your lists</flux:subheading>

                @if ($lists->isEmpty())
                    <flux:text size="sm" variant="subtle">No lists yet. Create one below.</flux:text>
                @else
                    <ul x-ref="listOl" class="space-y-1">
                        @foreach ($lists as $list)
                            <li wire:key="list-row-{{ $list->id }}"
                                data-list-id="{{ $list->id }}"
                                draggable="true"
                                @dragstart="onListDragStart($event, {{ $list->id }})"
                                @dragend="onListDragEnd()"
                                @dragover="onListDragOver($event, {{ $list->id }})"
                                @drop="onListDrop($event, {{ $list->id }})"
                                x-bind:class="(draggingListId === {{ $list->id }}) ? 'opacity-40' : (dragOverList === {{ $list->id }} ? 'ring-2 ring-indigo-400' : '')"
                                class="flex items-center gap-1 rounded">
                                <button type="button" wire:click="selectList({{ $list->id }})"
                                    class="flex-1 text-left px-2 py-1.5 rounded text-sm flex items-center gap-2 cursor-grab active:cursor-grabbing
                                    {{ $selected && $selected->id === $list->id ? 'bg-zinc-100 dark:bg-zinc-800 font-semibold' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/60' }}">
                                    <span class="size-2.5 rounded-full {{ $colorClass($list->color) }} shrink-0"></span>
                                    <span class="truncate flex-1">{{ $list->name }}</span>
                                    @if ($list->open_count > 0)
                                        <flux:badge size="sm" :color="$list->color ?: 'zinc'">{{ $list->open_count }}</flux:badge>
                                    @endif
                                </button>
                                <flux:dropdown align="end">
                                    <flux:button size="xs" variant="ghost" icon="ellipsis-vertical" />
                                    <flux:menu>
                                        <flux:menu.group heading="Color">
                                            <div class="grid grid-cols-5 gap-1 px-2 py-1.5">
                                                @foreach ($colors as $c)
                                                    <button type="button"
                                                        wire:click="setListColor({{ $list->id }}, '{{ $c }}')"
                                                        class="size-5 rounded-full {{ $colorClass($c) }} {{ $list->color === $c ? 'ring-2 ring-offset-1 ring-zinc-700 dark:ring-zinc-200' : '' }}"
                                                        title="{{ ucfirst($c) }}"></button>
                                                @endforeach
                                            </div>
                                        </flux:menu.group>
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger"
                                            wire:click="deleteList({{ $list->id }})"
                                            wire:confirm="Delete list '{{ $list->name }}' and all its items?">
                                            Delete list
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <form wire:submit="createList" class="mt-3 space-y-2">
                    <flux:input wire:model="newListName" placeholder="New list name" size="sm" />
                    <div class="flex items-center gap-1">
                        @foreach ($colors as $c)
                            <button type="button"
                                wire:click="$set('newListColor', '{{ $c }}')"
                                class="size-5 rounded-full {{ $colorClass($c) }} {{ $newListColor === $c ? 'ring-2 ring-offset-1 ring-zinc-700 dark:ring-zinc-200' : '' }}"
                                title="{{ ucfirst($c) }}"></button>
                        @endforeach
                        <flux:spacer />
                        <flux:button type="submit" variant="primary" size="sm" icon="plus" />
                    </div>
                </form>
            </flux:card>
        </div>

        {{-- ============ ITEM PANE ============ --}}
        <div class="space-y-4">
            @if (! $selected)
                <flux:card class="text-center py-12">
                    <flux:text variant="subtle">Select or create a list to get started.</flux:text>
                </flux:card>
            @else
                <flux:card>
                    <form wire:submit="addItem" class="space-y-3">
                        <flux:input wire:model="newItemTitle" placeholder="Add a task..." />

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <flux:input type="date" wire:model="newItemDueDate" label="Due date" />
                            <flux:select wire:model="newItemFrequency" label="Repeat">
                                <option value="">Doesn't repeat</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                            </flux:select>
                            <flux:input type="number" min="1" wire:model="newItemInterval" label="Every (N)" />
                        </div>

                        @if ($members->isNotEmpty())
                            <div>
                                <flux:label>Assign to</flux:label>
                                <div class="mt-1 flex flex-wrap gap-2">
                                    @foreach ($members as $member)
                                        <label class="inline-flex items-center gap-1.5 px-2 py-1 rounded border border-zinc-200 dark:border-zinc-700 text-sm cursor-pointer">
                                            <input type="checkbox" value="{{ $member->id }}" wire:model="newItemAssignees" class="rounded">
                                            {{ $member->name }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="flex justify-end">
                            <flux:button type="submit" variant="primary" icon="plus">Add</flux:button>
                        </div>
                    </form>
                </flux:card>

                <flux:card class="p-0!">
                    @if ($items->isEmpty())
                        <div class="p-8 text-center">
                            <flux:text variant="subtle">No items yet. Add one above.</flux:text>
                        </div>
                    @else
                        <ul wire:key="items-{{ $selected->id }}"
                            x-data="{
                                draggingId: null,
                                dragOver: null,
                                onStart(e, id) {
                                    this.draggingId = id;
                                    e.dataTransfer.effectAllowed = 'move';
                                    e.dataTransfer.setData('application/x-item-id', String(id));
                                },
                                onEnd() { this.draggingId = null; this.dragOver = null; },
                                onOver(e, id) { if (this.draggingId === null) return; e.preventDefault(); e.dataTransfer.dropEffect = 'move'; this.dragOver = id; },
                                onDrop(e, id) {
                                    e.preventDefault();
                                    if (this.draggingId === null) return;
                                    const ids = Array.from(this.$refs.itemsOl.querySelectorAll('[data-item-id]')).map(el => parseInt(el.dataset.itemId));
                                    const from = ids.indexOf(this.draggingId);
                                    const to = ids.indexOf(id);
                                    if (from > -1 && to > -1 && from !== to) {
                                        ids.splice(to, 0, ids.splice(from, 1)[0]);
                                        $wire.reorderItems(ids);
                                    }
                                    this.draggingId = null; this.dragOver = null;
                                },
                            }"
                            x-ref="itemsOl"
                            class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($items as $item)
                                <li wire:key="item-{{ $item->id }}"
                                    data-item-id="{{ $item->id }}"
                                    draggable="{{ $editingItemId === $item->id ? 'false' : 'true' }}"
                                    @dragstart="onStart($event, {{ $item->id }})"
                                    @dragend="onEnd()"
                                    @dragover="onOver($event, {{ $item->id }})"
                                    @drop="onDrop($event, {{ $item->id }})"
                                    x-bind:class="(draggingId === {{ $item->id }}) ? 'opacity-40' : (dragOver === {{ $item->id }} ? 'bg-indigo-50 dark:bg-indigo-900/20' : '')"
                                    class="p-3 sm:p-4">
                                    @if ($editingItemId === $item->id)
                                        <div class="space-y-3">
                                            <flux:input wire:model="editForm.title" label="Title" />
                                            <flux:textarea wire:model="editForm.notes" label="Notes" rows="2" />
                                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                                <flux:input type="date" wire:model="editForm.due_date" label="Due date" />
                                                <flux:select wire:model="editForm.recurrence_frequency" label="Repeat">
                                                    <option value="">Doesn't repeat</option>
                                                    <option value="daily">Daily</option>
                                                    <option value="weekly">Weekly</option>
                                                    <option value="monthly">Monthly</option>
                                                    <option value="yearly">Yearly</option>
                                                </flux:select>
                                                <flux:input type="number" min="1" wire:model="editForm.recurrence_interval" label="Every (N)" />
                                            </div>
                                            <flux:input type="date" wire:model="editForm.recurrence_until" label="Repeat until (optional)" />
                                            @if ($members->isNotEmpty())
                                                <div>
                                                    <flux:label>Assign to</flux:label>
                                                    <div class="mt-1 flex flex-wrap gap-2">
                                                        @foreach ($members as $member)
                                                            <label class="inline-flex items-center gap-1.5 px-2 py-1 rounded border border-zinc-200 dark:border-zinc-700 text-sm cursor-pointer">
                                                                <input type="checkbox" value="{{ $member->id }}" wire:model="editForm.assignees" class="rounded">
                                                                {{ $member->name }}
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                            <div class="flex justify-end gap-2">
                                                <flux:button size="sm" variant="ghost" wire:click="cancelEdit">Cancel</flux:button>
                                                <flux:button size="sm" variant="primary" wire:click="saveEdit">Save</flux:button>
                                            </div>
                                        </div>
                                    @else
                                        <div class="flex items-start gap-3">
                                            <flux:icon icon="bars-2" class="size-4 mt-1.5 text-zinc-300 dark:text-zinc-600 shrink-0 cursor-grab" />
                                            <button type="button" wire:click="toggleComplete({{ $item->id }})"
                                                class="mt-0.5 size-5 shrink-0 rounded border-2 flex items-center justify-center transition-colors
                                                {{ $item->isCompleted() ? 'bg-emerald-500 border-emerald-500 text-white' : 'border-zinc-300 dark:border-zinc-600 hover:border-emerald-500' }}">
                                                @if ($item->isCompleted())
                                                    <flux:icon icon="check" class="size-3.5" />
                                                @endif
                                            </button>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="text-sm {{ $item->isCompleted() ? 'line-through text-zinc-400' : 'font-medium' }}">
                                                        {{ $item->title }}
                                                    </span>
                                                    @if ($item->isRecurring())
                                                        <flux:badge size="sm" color="indigo" icon="arrow-path">
                                                            @php
                                                                $unit = match ($item->recurrence_frequency) {
                                                                    'daily' => 'day',
                                                                    'weekly' => 'week',
                                                                    'monthly' => 'month',
                                                                    'yearly' => 'year',
                                                                    default => $item->recurrence_frequency,
                                                                };
                                                                $n = $item->recurrence_interval ?? 1;
                                                            @endphp
                                                            {{ $n > 1 ? "Every {$n} ".\Illuminate\Support\Str::plural($unit, $n) : ucfirst($item->recurrence_frequency) }}
                                                        </flux:badge>
                                                    @endif
                                                    @if ($item->due_date)
                                                        @php
                                                            $isOverdue = ! $item->isCompleted() && $item->due_date->isPast() && ! $item->due_date->isToday();
                                                            $isToday = $item->due_date->isToday();
                                                        @endphp
                                                        <flux:badge size="sm" :color="$isOverdue ? 'red' : ($isToday ? 'amber' : 'zinc')" icon="calendar">
                                                            {{ $item->due_date->format('M j') }}
                                                        </flux:badge>
                                                    @endif
                                                </div>
                                                @if ($item->notes)
                                                    <flux:text size="sm" variant="subtle" class="mt-1 whitespace-pre-line">{{ $item->notes }}</flux:text>
                                                @endif
                                                @if ($item->assignees->isNotEmpty())
                                                    <div class="mt-1 flex flex-wrap gap-1">
                                                        @foreach ($item->assignees as $assignee)
                                                            <flux:badge size="sm" color="zinc">{{ $assignee->name }}</flux:badge>
                                                        @endforeach
                                                    </div>
                                                @endif
                                                @if ($item->isCompleted() && $item->completedBy)
                                                    <flux:text size="xs" variant="subtle" class="mt-1">
                                                        Completed by {{ $item->completedBy->name }} {{ $item->completed_at->diffForHumans() }}
                                                    </flux:text>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-1">
                                                <flux:button size="xs" variant="ghost" icon="pencil" wire:click="startEdit({{ $item->id }})" />
                                                <flux:button size="xs" variant="ghost" icon="trash"
                                                    wire:click="deleteItem({{ $item->id }})"
                                                    wire:confirm="Delete this item?" />
                                            </div>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </flux:card>
            @endif
        </div>
    </div>
</div>
