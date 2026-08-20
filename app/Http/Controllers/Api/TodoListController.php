<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TodoList;
use App\Support\ApiPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TodoListController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->lists($request)->map(
            fn (TodoList $list) => ApiPayload::todoList($list)
        )->values());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['nullable', Rule::in(TodoList::COLORS)],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $household = $request->user()->household;
        abort_unless($household, 403);

        $list = $household->todoLists()->create([
            'name' => $data['name'],
            'color' => $data['color'] ?? 'indigo',
            'description' => $data['description'] ?? null,
            'position' => ($household->todoLists()->max('position') ?? 0) + 1,
        ]);

        return response()->json(ApiPayload::todoList($list), 201);
    }

    public function update(Request $request, TodoList $list): JsonResponse
    {
        $this->authorizeList($request, $list);

        $list->update($request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'color' => ['sometimes', Rule::in(TodoList::COLORS)],
            'description' => ['nullable', 'string', 'max:500'],
        ]));

        return response()->json(ApiPayload::todoList($list));
    }

    public function destroy(Request $request, TodoList $list): JsonResponse
    {
        $this->authorizeList($request, $list);
        $list->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Take the order the phone dragged the lists into, ignoring anything that
     * is not ours to move.
     */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['integer'],
        ]);

        $household = $request->user()->household;
        abort_unless($household, 403);

        $owned = array_flip($household->todoLists()->pluck('id')->all());
        $position = 1;

        foreach (array_values(array_unique($data['ordered_ids'])) as $id) {
            if (! isset($owned[$id])) {
                continue;
            }
            TodoList::where('id', $id)->update(['position' => $position++]);
        }

        return response()->json($this->lists($request)->map(
            fn (TodoList $list) => ApiPayload::todoList($list)
        )->values());
    }

    private function lists(Request $request)
    {
        $household = $request->user()->household;

        if (! $household) {
            return collect();
        }

        return $household->todoLists()
            ->withCount(['items as open_count' => fn ($query) => $query->whereNull('completed_at')])
            ->get();
    }

    private function authorizeList(Request $request, TodoList $list): void
    {
        abort_unless($list->household_id === $request->user()->household_id, 403);
    }
}
