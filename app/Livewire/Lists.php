<?php

namespace App\Livewire;

use App\Models\TodoItem;
use App\Models\TodoList;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Lists extends Component
{
    #[Url(as: 'list')]
    public ?int $selectedListId = null;

    public string $newListName = '';

    public string $newListColor = 'indigo';

    public const LIST_COLORS = ['zinc', 'red', 'orange', 'amber', 'lime', 'emerald', 'sky', 'indigo', 'violet', 'pink'];

    public string $newItemTitle = '';

    public ?string $newItemDueDate = null;

    public string $newItemFrequency = '';

    public int $newItemInterval = 1;

    /** @var array<int> */
    public array $newItemAssignees = [];

    public ?int $editingItemId = null;

    public array $editForm = [
        'title' => '',
        'notes' => '',
        'due_date' => null,
        'recurrence_frequency' => '',
        'recurrence_interval' => 1,
        'recurrence_until' => null,
        'assignees' => [],
    ];

    public function mount(): void
    {
        $first = $this->householdLists()->first();
        if ($this->selectedListId === null && $first) {
            $this->selectedListId = $first->id;
        }
    }

    public function householdLists()
    {
        return auth()->user()->household?->todoLists ?? collect();
    }

    public function selectList(int $listId): void
    {
        $list = $this->householdLists()->firstWhere('id', $listId);
        if ($list) {
            $this->selectedListId = $list->id;
            $this->cancelEdit();
        }
    }

    public function createList(): void
    {
        $this->validate([
            'newListName' => ['required', 'string', 'max:120'],
        ]);

        $household = auth()->user()->household;
        abort_unless($household, 403);

        $list = $household->todoLists()->create([
            'name' => $this->newListName,
            'color' => in_array($this->newListColor, self::LIST_COLORS, true) ? $this->newListColor : 'indigo',
            'position' => ($household->todoLists()->max('position') ?? 0) + 1,
        ]);

        $this->newListName = '';
        $this->selectedListId = $list->id;
    }

    public function setListColor(int $listId, string $color): void
    {
        if (! in_array($color, self::LIST_COLORS, true)) {
            return;
        }
        $list = $this->ensureOwnedList($listId);
        $list->update(['color' => $color]);
    }

    public function reorderLists(array $orderedIds): void
    {
        $household = auth()->user()->household;
        if (! $household) {
            return;
        }

        $ids = array_values(array_unique(array_map('intval', $orderedIds)));
        $owned = $household->todoLists()->whereIn('id', $ids)->pluck('id')->all();
        $owned = array_flip($owned);

        $position = 1;
        foreach ($ids as $id) {
            if (! isset($owned[$id])) {
                continue;
            }
            TodoList::where('id', $id)->update(['position' => $position++]);
        }
    }

    public function reorderItems(array $orderedIds): void
    {
        if ($this->selectedListId === null) {
            return;
        }
        $list = $this->ensureOwnedList($this->selectedListId);

        $ids = array_values(array_unique(array_map('intval', $orderedIds)));
        $owned = array_flip($list->items()->whereIn('id', $ids)->pluck('id')->all());

        $position = 1;
        foreach ($ids as $id) {
            if (! isset($owned[$id])) {
                continue;
            }
            TodoItem::where('id', $id)->update(['position' => $position++]);
        }
    }

    public function moveItemToList(int $itemId, int $listId): void
    {
        $item = $this->ensureOwnedItem($itemId);
        $list = $this->ensureOwnedList($listId);

        if ($item->todo_list_id === $list->id) {
            return;
        }

        $item->update([
            'todo_list_id' => $list->id,
            'position' => ($list->items()->max('position') ?? 0) + 1,
        ]);
    }

    public function deleteList(int $listId): void
    {
        $list = $this->ensureOwnedList($listId);
        $list->delete();

        if ($this->selectedListId === $listId) {
            $this->selectedListId = $this->householdLists()->first()?->id;
        }
    }

    public function addItem(): void
    {
        $this->validate([
            'selectedListId' => ['required', 'integer'],
            'newItemTitle' => ['required', 'string', 'max:255'],
            'newItemDueDate' => ['nullable', 'date'],
            'newItemFrequency' => ['nullable', Rule::in(['', 'daily', 'weekly', 'monthly', 'yearly'])],
            'newItemInterval' => ['nullable', 'integer', 'min:1', 'max:365'],
            'newItemAssignees' => ['array'],
            'newItemAssignees.*' => ['integer'],
        ]);

        $list = $this->ensureOwnedList($this->selectedListId);
        $memberIds = $this->validHouseholdMemberIds($this->newItemAssignees);

        $item = $list->items()->create([
            'title' => $this->newItemTitle,
            'due_date' => $this->newItemDueDate ?: null,
            'recurrence_frequency' => $this->newItemFrequency ?: null,
            'recurrence_interval' => $this->newItemFrequency ? max(1, (int) $this->newItemInterval) : null,
            'position' => ($list->items()->max('position') ?? 0) + 1,
        ]);

        if (! empty($memberIds)) {
            $item->assignees()->sync($memberIds);
        }

        $this->reset(['newItemTitle', 'newItemDueDate', 'newItemFrequency', 'newItemAssignees']);
        $this->newItemInterval = 1;
    }

    public function toggleComplete(int $itemId): void
    {
        $item = $this->ensureOwnedItem($itemId);

        if ($item->isCompleted()) {
            $item->update([
                'completed_at' => null,
                'completed_by_family_member_id' => null,
            ]);

            return;
        }

        $member = auth()->user()->familyMember;
        $item->update([
            'completed_at' => CarbonImmutable::now(),
            'completed_by_family_member_id' => $member?->id,
        ]);

        if ($item->isRecurring()) {
            $item->spawnNextOccurrence();
        }
    }

    public function deleteItem(int $itemId): void
    {
        $item = $this->ensureOwnedItem($itemId);
        $item->delete();

        if ($this->editingItemId === $itemId) {
            $this->cancelEdit();
        }
    }

    public function startEdit(int $itemId): void
    {
        $item = $this->ensureOwnedItem($itemId);
        $this->editingItemId = $item->id;
        $this->editForm = [
            'title' => $item->title,
            'notes' => $item->notes ?? '',
            'due_date' => $item->due_date?->toDateString(),
            'recurrence_frequency' => $item->recurrence_frequency ?? '',
            'recurrence_interval' => $item->recurrence_interval ?? 1,
            'recurrence_until' => $item->recurrence_until?->toDateString(),
            'assignees' => $item->assignees()->pluck('family_members.id')->all(),
        ];
    }

    public function cancelEdit(): void
    {
        $this->editingItemId = null;
    }

    public function saveEdit(): void
    {
        if ($this->editingItemId === null) {
            return;
        }

        $this->validate([
            'editForm.title' => ['required', 'string', 'max:255'],
            'editForm.notes' => ['nullable', 'string'],
            'editForm.due_date' => ['nullable', 'date'],
            'editForm.recurrence_frequency' => ['nullable', Rule::in(['', 'daily', 'weekly', 'monthly', 'yearly'])],
            'editForm.recurrence_interval' => ['nullable', 'integer', 'min:1', 'max:365'],
            'editForm.recurrence_until' => ['nullable', 'date'],
            'editForm.assignees' => ['array'],
            'editForm.assignees.*' => ['integer'],
        ]);

        $item = $this->ensureOwnedItem($this->editingItemId);
        $freq = $this->editForm['recurrence_frequency'] ?: null;

        $item->update([
            'title' => $this->editForm['title'],
            'notes' => $this->editForm['notes'] ?: null,
            'due_date' => $this->editForm['due_date'] ?: null,
            'recurrence_frequency' => $freq,
            'recurrence_interval' => $freq ? max(1, (int) $this->editForm['recurrence_interval']) : null,
            'recurrence_until' => $this->editForm['recurrence_until'] ?: null,
        ]);

        $item->assignees()->sync($this->validHouseholdMemberIds($this->editForm['assignees']));

        $this->cancelEdit();
    }

    protected function ensureOwnedList(int $listId): TodoList
    {
        $list = TodoList::query()->findOrFail($listId);
        abort_unless($list->household_id === auth()->user()->household_id, 403);

        return $list;
    }

    protected function ensureOwnedItem(int $itemId): TodoItem
    {
        $item = TodoItem::query()->with('list')->findOrFail($itemId);
        abort_unless($item->list && $item->list->household_id === auth()->user()->household_id, 403);

        return $item;
    }

    /**
     * @param  array<int|string>  $ids
     * @return array<int>
     */
    protected function validHouseholdMemberIds(array $ids): array
    {
        $household = auth()->user()->household;
        if (! $household) {
            return [];
        }

        return $household->members()
            ->whereIn('id', array_map('intval', $ids))
            ->pluck('id')
            ->all();
    }

    public function render()
    {
        $household = auth()->user()->household;
        $lists = $household ? $household->todoLists()->withCount(['items as open_count' => function ($q) {
            $q->whereNull('completed_at');
        }])->get() : collect();

        $selected = $this->selectedListId
            ? $lists->firstWhere('id', $this->selectedListId)
            : null;

        $items = collect();
        if ($selected) {
            $items = $selected->items()
                ->with('assignees', 'completedBy')
                ->orderByRaw('completed_at IS NULL DESC')
                ->orderByRaw('due_date IS NULL, due_date ASC')
                ->orderBy('position')
                ->orderBy('id')
                ->get();
        }

        $members = $household ? $household->members()->orderBy('name')->get() : collect();

        return view('livewire.lists', [
            'lists' => $lists,
            'selected' => $selected,
            'items' => $items,
            'members' => $members,
        ]);
    }
}
