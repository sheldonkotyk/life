<?php

use App\Livewire\Lists;
use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\TodoItem;
use App\Models\TodoList;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

it('creates a list and selects it', function () {
    $user = loginUser();

    Livewire::test(Lists::class)
        ->set('newListName', 'Chores')
        ->call('createList')
        ->assertSet('newListName', '');

    $list = TodoList::where('household_id', $user->household_id)->first();
    expect($list->name)->toBe('Chores');
});

it('adds an item with assignees and recurrence', function () {
    $user = loginUser();
    $list = TodoList::create([
        'household_id' => $user->household_id,
        'name' => 'House',
    ]);
    $member = FamilyMember::create([
        'household_id' => $user->household_id,
        'name' => 'Alex',
    ]);

    Livewire::test(Lists::class)
        ->set('selectedListId', $list->id)
        ->set('newItemTitle', 'Take out trash')
        ->set('newItemFrequency', 'weekly')
        ->set('newItemInterval', 1)
        ->set('newItemAssignees', [$member->id])
        ->call('addItem')
        ->assertSet('newItemTitle', '');

    $item = TodoItem::first();
    expect($item->title)->toBe('Take out trash');
    expect($item->recurrence_frequency)->toBe('weekly');
    expect($item->assignees->pluck('id')->all())->toBe([$member->id]);
});

it('completing a recurring item spawns the next occurrence', function () {
    $user = loginUser();
    $member = FamilyMember::create([
        'household_id' => $user->household_id,
        'name' => 'Sam',
        'user_id' => $user->id,
    ]);

    $list = TodoList::create([
        'household_id' => $user->household_id,
        'name' => 'Routine',
    ]);
    $item = $list->items()->create([
        'title' => 'Water plants',
        'due_date' => CarbonImmutable::parse('2026-05-01')->toDateString(),
        'recurrence_frequency' => 'weekly',
        'recurrence_interval' => 1,
    ]);

    Livewire::test(Lists::class)
        ->set('selectedListId', $list->id)
        ->call('toggleComplete', $item->id);

    $item->refresh();
    expect($item->completed_at)->not->toBeNull();
    expect($item->completed_by_family_member_id)->toBe($member->id);

    $next = TodoItem::where('id', '!=', $item->id)->first();
    expect($next)->not->toBeNull();
    expect($next->title)->toBe('Water plants');
    expect($next->due_date->toDateString())->toBe('2026-05-08');
    expect($next->completed_at)->toBeNull();
});

it('toggles non-recurring item complete state', function () {
    $user = loginUser();
    $list = TodoList::create([
        'household_id' => $user->household_id,
        'name' => 'Today',
    ]);
    $item = $list->items()->create(['title' => 'Email Bob']);

    Livewire::test(Lists::class)
        ->set('selectedListId', $list->id)
        ->call('toggleComplete', $item->id);
    expect($item->fresh()->completed_at)->not->toBeNull();
    expect(TodoItem::count())->toBe(1);

    Livewire::test(Lists::class)
        ->set('selectedListId', $list->id)
        ->call('toggleComplete', $item->id);
    expect($item->fresh()->completed_at)->toBeNull();
});

it('forbids accessing a list from another household', function () {
    loginUser();
    $otherHousehold = Household::create(['name' => 'Outsiders']);
    $foreignList = TodoList::create([
        'household_id' => $otherHousehold->id,
        'name' => 'Secret',
    ]);

    Livewire::test(Lists::class)
        ->call('deleteList', $foreignList->id)
        ->assertStatus(403);
});

it('reorders lists', function () {
    $user = loginUser();
    $a = TodoList::create(['household_id' => $user->household_id, 'name' => 'A', 'position' => 1]);
    $b = TodoList::create(['household_id' => $user->household_id, 'name' => 'B', 'position' => 2]);
    $c = TodoList::create(['household_id' => $user->household_id, 'name' => 'C', 'position' => 3]);

    Livewire::test(Lists::class)->call('reorderLists', [$c->id, $a->id, $b->id]);

    expect($c->fresh()->position)->toBe(1);
    expect($a->fresh()->position)->toBe(2);
    expect($b->fresh()->position)->toBe(3);
});

it('reorders items within a list', function () {
    $user = loginUser();
    $list = TodoList::create(['household_id' => $user->household_id, 'name' => 'X']);
    $i1 = $list->items()->create(['title' => 'one', 'position' => 1]);
    $i2 = $list->items()->create(['title' => 'two', 'position' => 2]);
    $i3 = $list->items()->create(['title' => 'three', 'position' => 3]);

    Livewire::test(Lists::class)
        ->set('selectedListId', $list->id)
        ->call('reorderItems', [$i3->id, $i1->id, $i2->id]);

    expect($i3->fresh()->position)->toBe(1);
    expect($i1->fresh()->position)->toBe(2);
    expect($i2->fresh()->position)->toBe(3);
});

it('moves an item to another list', function () {
    $user = loginUser();
    $a = TodoList::create(['household_id' => $user->household_id, 'name' => 'A']);
    $b = TodoList::create(['household_id' => $user->household_id, 'name' => 'B']);
    $item = $a->items()->create(['title' => 'wandering']);

    Livewire::test(Lists::class)
        ->set('selectedListId', $a->id)
        ->call('moveItemToList', $item->id, $b->id);

    expect($item->fresh()->todo_list_id)->toBe($b->id);
});

it('changes a list color', function () {
    $user = loginUser();
    $list = TodoList::create(['household_id' => $user->household_id, 'name' => 'A', 'color' => 'indigo']);

    Livewire::test(Lists::class)->call('setListColor', $list->id, 'pink');
    expect($list->fresh()->color)->toBe('pink');

    Livewire::test(Lists::class)->call('setListColor', $list->id, 'not-a-color');
    expect($list->fresh()->color)->toBe('pink');
});

it('edits an item and updates its recurrence and assignees', function () {
    $user = loginUser();
    $member = FamilyMember::create([
        'household_id' => $user->household_id,
        'name' => 'Riley',
    ]);
    $list = TodoList::create([
        'household_id' => $user->household_id,
        'name' => 'Edit me',
    ]);
    $item = $list->items()->create(['title' => 'Old title']);

    Livewire::test(Lists::class)
        ->set('selectedListId', $list->id)
        ->call('startEdit', $item->id)
        ->set('editForm.title', 'New title')
        ->set('editForm.recurrence_frequency', 'monthly')
        ->set('editForm.recurrence_interval', 2)
        ->set('editForm.assignees', [$member->id])
        ->call('saveEdit')
        ->assertSet('editingItemId', null);

    $item->refresh();
    expect($item->title)->toBe('New title');
    expect($item->recurrence_frequency)->toBe('monthly');
    expect($item->recurrence_interval)->toBe(2);
    expect($item->assignees->pluck('id')->all())->toBe([$member->id]);
});
