<?php

use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\TodoItem;
use App\Models\TodoList;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-08-19 15:00:00 UTC');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('lists the household lists with their open counts', function () {
    $user = loginApiUser();
    $list = TodoList::create(['household_id' => $user->household_id, 'name' => 'Chores']);
    $list->items()->create(['title' => 'Open']);
    $list->items()->create(['title' => 'Done', 'completed_at' => now()]);

    $response = $this->getJson('/api/todo-lists')->assertOk();

    expect($response->json())->toHaveCount(1)
        ->and($response->json('0.name'))->toBe('Chores')
        ->and($response->json('0.open_count'))->toBe(1);
});

it('creates a list at the end of the order', function () {
    $user = loginApiUser();
    TodoList::create(['household_id' => $user->household_id, 'name' => 'First', 'position' => 4]);

    $this->postJson('/api/todo-lists', ['name' => 'Second', 'color' => 'emerald'])
        ->assertStatus(201)
        ->assertJsonPath('position', 5)
        ->assertJsonPath('color', 'emerald');
});

it('refuses a colour outside the palette', function () {
    loginApiUser();
    $this->postJson('/api/todo-lists', ['name' => 'X', 'color' => 'chartreuse'])->assertStatus(422);
});

it('reorders lists and ignores ids from elsewhere', function () {
    $user = loginApiUser();
    $a = TodoList::create(['household_id' => $user->household_id, 'name' => 'A', 'position' => 1]);
    $b = TodoList::create(['household_id' => $user->household_id, 'name' => 'B', 'position' => 2]);
    $foreign = TodoList::create(['household_id' => Household::create(['name' => 'X'])->id, 'name' => 'F']);

    $this->postJson('/api/todo-lists/reorder', ['ordered_ids' => [$b->id, $foreign->id, $a->id]])->assertOk();

    expect($b->fresh()->position)->toBe(1)
        ->and($a->fresh()->position)->toBe(2);
});

it('blocks touching a list from another household', function () {
    loginApiUser();
    $foreign = TodoList::create(['household_id' => Household::create(['name' => 'X'])->id, 'name' => 'F']);

    $this->patchJson("/api/todo-lists/{$foreign->id}", ['name' => 'Hijack'])->assertStatus(403);
    $this->deleteJson("/api/todo-lists/{$foreign->id}")->assertStatus(403);
});

it('adds an item with assignees drawn from the household', function () {
    $user = loginApiUser();
    $list = TodoList::create(['household_id' => $user->household_id, 'name' => 'Chores']);
    $member = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Kid']);
    $foreign = FamilyMember::create(['household_id' => Household::create(['name' => 'X'])->id, 'name' => 'Nope']);

    $response = $this->postJson("/api/todo-lists/{$list->id}/items", [
        'title' => 'Dishes',
        'due_date' => '2026-08-20',
        'assignee_ids' => [$member->id, $foreign->id],
    ])->assertStatus(201);

    expect($response->json('assignee_ids'))->toBe([$member->id])
        ->and($response->json('due_date'))->toBe('2026-08-20');
});

it('completes an item and stamps who did it', function () {
    $user = loginApiUser();
    $me = FamilyMember::create(['household_id' => $user->household_id, 'user_id' => $user->id, 'name' => 'Me']);
    $list = TodoList::create(['household_id' => $user->household_id, 'name' => 'Chores']);
    $item = $list->items()->create(['title' => 'Bins']);

    $response = $this->postJson("/api/todo-items/{$item->id}/toggle")->assertOk();

    expect($response->json('item.completed_at'))->not->toBeNull()
        ->and($response->json('item.completed_by_family_member_id'))->toBe($me->id)
        ->and($response->json('spawned'))->toBeNull();
});

it('untoggles a completed item', function () {
    $user = loginApiUser();
    $list = TodoList::create(['household_id' => $user->household_id, 'name' => 'Chores']);
    $item = $list->items()->create(['title' => 'Bins', 'completed_at' => now()]);

    $this->postJson("/api/todo-items/{$item->id}/toggle")
        ->assertOk()
        ->assertJsonPath('item.completed_at', null);
});

it('lays down the next occurrence when a recurring job is done', function () {
    $user = loginApiUser();
    $list = TodoList::create(['household_id' => $user->household_id, 'name' => 'Chores']);
    $item = $list->items()->create([
        'title' => 'Bins',
        'due_date' => '2026-08-19',
        'recurrence_frequency' => 'weekly',
        'recurrence_interval' => 1,
    ]);

    $response = $this->postJson("/api/todo-items/{$item->id}/toggle")->assertOk();

    expect($response->json('spawned.due_date'))->toBe('2026-08-26')
        ->and($response->json('spawned.completed_at'))->toBeNull()
        ->and(TodoItem::where('todo_list_id', $list->id)->count())->toBe(2);
});

it('moves an item to another list', function () {
    $user = loginApiUser();
    $from = TodoList::create(['household_id' => $user->household_id, 'name' => 'From']);
    $to = TodoList::create(['household_id' => $user->household_id, 'name' => 'To']);
    $item = $from->items()->create(['title' => 'Travelling']);

    $this->postJson("/api/todo-items/{$item->id}/move", ['todo_list_id' => $to->id])
        ->assertOk()
        ->assertJsonPath('todo_list_id', $to->id);
});

it('refuses to move an item into another household\'s list', function () {
    $user = loginApiUser();
    $from = TodoList::create(['household_id' => $user->household_id, 'name' => 'From']);
    $item = $from->items()->create(['title' => 'Stay']);
    $foreign = TodoList::create(['household_id' => Household::create(['name' => 'X'])->id, 'name' => 'F']);

    $this->postJson("/api/todo-items/{$item->id}/move", ['todo_list_id' => $foreign->id])->assertStatus(403);
});

it('reorders items within a list', function () {
    $user = loginApiUser();
    $list = TodoList::create(['household_id' => $user->household_id, 'name' => 'Chores']);
    $a = $list->items()->create(['title' => 'A', 'position' => 1]);
    $b = $list->items()->create(['title' => 'B', 'position' => 2]);

    $this->postJson("/api/todo-lists/{$list->id}/items/reorder", ['ordered_ids' => [$b->id, $a->id]])->assertOk();

    expect($b->fresh()->position)->toBe(1)
        ->and($a->fresh()->position)->toBe(2);
});

it('edits an item and clears its recurrence when the frequency goes', function () {
    $user = loginApiUser();
    $list = TodoList::create(['household_id' => $user->household_id, 'name' => 'Chores']);
    $item = $list->items()->create([
        'title' => 'Bins',
        'recurrence_frequency' => 'weekly',
        'recurrence_interval' => 2,
    ]);

    $this->patchJson("/api/todo-items/{$item->id}", [
        'title' => 'Bins out',
        'recurrence_frequency' => null,
    ])->assertOk();

    expect($item->fresh()->title)->toBe('Bins out')
        ->and($item->fresh()->recurrence_frequency)->toBeNull()
        ->and($item->fresh()->recurrence_interval)->toBeNull();
});

it('deletes an item', function () {
    $user = loginApiUser();
    $list = TodoList::create(['household_id' => $user->household_id, 'name' => 'Chores']);
    $item = $list->items()->create(['title' => 'Gone']);

    $this->deleteJson("/api/todo-items/{$item->id}")->assertOk();
    expect(TodoItem::find($item->id))->toBeNull();
});
