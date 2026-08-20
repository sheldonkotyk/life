<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TodoItem;
use App\Models\TodoList;
use App\Support\ApiPayload;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TodoItemController extends Controller
{
    private const FREQUENCIES = ['daily', 'weekly', 'monthly', 'yearly'];

    public function index(Request $request, TodoList $list): JsonResponse
    {
        $this->authorizeList($request, $list);

        $items = $list->items()
            ->with('assignees')
            ->orderByRaw('completed_at IS NULL DESC')
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return response()->json($items->map(fn (TodoItem $item) => ApiPayload::todoItem($item)));
    }

    public function store(Request $request, TodoList $list): JsonResponse
    {
        $this->authorizeList($request, $list);
        $data = $this->validateItem($request, creating: true);

        $item = $list->items()->create([
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'recurrence_frequency' => $data['recurrence_frequency'] ?? null,
            'recurrence_interval' => isset($data['recurrence_frequency'])
                ? max(1, (int) ($data['recurrence_interval'] ?? 1))
                : null,
            'recurrence_until' => $data['recurrence_until'] ?? null,
            'position' => ($list->items()->max('position') ?? 0) + 1,
        ]);

        $item->assignees()->sync($this->validMemberIds($request, $data['assignee_ids'] ?? []));

        return response()->json(ApiPayload::todoItem($item->load('assignees')), 201);
    }

    public function update(Request $request, TodoItem $item): JsonResponse
    {
        $this->authorizeItem($request, $item);
        $data = $this->validateItem($request, creating: false);

        $changes = collect($data)->except('assignee_ids')->all();

        if (array_key_exists('recurrence_frequency', $changes)) {
            $changes['recurrence_interval'] = $changes['recurrence_frequency']
                ? max(1, (int) ($data['recurrence_interval'] ?? $item->recurrence_interval ?? 1))
                : null;
        }

        $item->update($changes);

        if (array_key_exists('assignee_ids', $data)) {
            $item->assignees()->sync($this->validMemberIds($request, $data['assignee_ids'] ?? []));
        }

        return response()->json(ApiPayload::todoItem($item->fresh()->load('assignees')));
    }

    public function destroy(Request $request, TodoItem $item): JsonResponse
    {
        $this->authorizeItem($request, $item);
        $item->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Tick or untick. Completing a recurring job immediately lays down the next
     * one, which is what stops a weekly chore from vanishing when it is done.
     */
    public function toggle(Request $request, TodoItem $item): JsonResponse
    {
        $this->authorizeItem($request, $item);

        if ($item->isCompleted()) {
            $item->update(['completed_at' => null, 'completed_by_family_member_id' => null]);

            return response()->json([
                'item' => ApiPayload::todoItem($item->fresh()->load('assignees')),
                'spawned' => null,
            ]);
        }

        $item->update([
            'completed_at' => CarbonImmutable::now(),
            'completed_by_family_member_id' => $request->user()->familyMember?->id,
        ]);

        $spawned = $item->isRecurring() ? $item->spawnNextOccurrence() : null;

        return response()->json([
            'item' => ApiPayload::todoItem($item->fresh()->load('assignees')),
            'spawned' => $spawned ? ApiPayload::todoItem($spawned->load('assignees')) : null,
        ]);
    }

    public function move(Request $request, TodoItem $item): JsonResponse
    {
        $this->authorizeItem($request, $item);

        $data = $request->validate(['todo_list_id' => ['required', 'integer']]);
        $list = TodoList::findOrFail($data['todo_list_id']);
        $this->authorizeList($request, $list);

        if ($item->todo_list_id !== $list->id) {
            $item->update([
                'todo_list_id' => $list->id,
                'position' => ($list->items()->max('position') ?? 0) + 1,
            ]);
        }

        return response()->json(ApiPayload::todoItem($item->fresh()->load('assignees')));
    }

    public function reorder(Request $request, TodoList $list): JsonResponse
    {
        $this->authorizeList($request, $list);

        $data = $request->validate([
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['integer'],
        ]);

        $owned = array_flip($list->items()->pluck('id')->all());
        $position = 1;

        foreach (array_values(array_unique($data['ordered_ids'])) as $id) {
            if (! isset($owned[$id])) {
                continue;
            }
            TodoItem::where('id', $id)->update(['position' => $position++]);
        }

        return $this->index($request, $list);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateItem(Request $request, bool $creating): array
    {
        return $request->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'recurrence_frequency' => ['nullable', Rule::in(self::FREQUENCIES)],
            'recurrence_interval' => ['nullable', 'integer', 'min:1', 'max:365'],
            'recurrence_until' => ['nullable', 'date'],
            'assignee_ids' => ['sometimes', 'array'],
            'assignee_ids.*' => ['integer'],
        ]);
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array<int, int>
     */
    private function validMemberIds(Request $request, array $ids): array
    {
        $household = $request->user()->household;

        if (! $household) {
            return [];
        }

        return $household->members()
            ->whereIn('id', array_map('intval', $ids))
            ->pluck('id')
            ->all();
    }

    private function authorizeList(Request $request, TodoList $list): void
    {
        abort_unless($list->household_id === $request->user()->household_id, 403);
    }

    private function authorizeItem(Request $request, TodoItem $item): void
    {
        $item->loadMissing('list');
        abort_unless($item->list && $item->list->household_id === $request->user()->household_id, 403);
    }
}
