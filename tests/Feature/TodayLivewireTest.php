<?php

use App\Livewire\Today;
use App\Models\FamilyMember;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\TodoItem;
use App\Models\TodoList;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

function tonightDinner(int $householdId, int $servings = 4, ?int $prep = 30): MealPlan
{
    $recipe = Recipe::create([
        'household_id' => $householdId,
        'name' => 'Stir Fry',
        'servings' => $servings,
        'prep_minutes' => $prep,
    ]);

    return MealPlan::create([
        'household_id' => $householdId,
        'date' => CarbonImmutable::today()->toDateString(),
        'slot' => 'dinner',
        'recipe_id' => $recipe->id,
    ]);
}

it('renders tonight dashboard with no plan', function () {
    loginUser();

    Livewire::test(Today::class)
        ->assertSee('Nothing planned today');
});

it('shows tonight dinner with confirmed count', function () {
    $user = loginUser();
    $plan = tonightDinner($user->household_id);
    $alex = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Alex']);
    $sam = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Sam']);
    $plan->attendees()->attach([$alex->id => ['status' => 'eating'], $sam->id => ['status' => 'eating']]);

    Livewire::test(Today::class)
        ->assertSee('Stir Fry')
        ->assertSee('2 confirmed');
});

it('lets the logged-in user mark themselves running late', function () {
    $user = loginUser();
    $plan = tonightDinner($user->household_id);
    $me = FamilyMember::create([
        'household_id' => $user->household_id,
        'user_id' => $user->id,
        'name' => 'Me',
    ]);

    Livewire::test(Today::class)
        ->call('setMyStatus', $plan->id, 'running_late');

    expect($plan->attendees()->where('family_members.id', $me->id)->first()?->pivot->status)
        ->toBe('running_late');
});

it('counts running-late attendees as confirmed', function () {
    $user = loginUser();
    $plan = tonightDinner($user->household_id);
    $alex = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Alex']);
    $sam = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Sam']);
    $plan->attendees()->attach([
        $alex->id => ['status' => 'eating'],
        $sam->id => ['status' => 'running_late'],
    ]);

    Livewire::test(Today::class)
        ->assertSee('2 confirmed')
        ->assertSee('1 running late');
});

it('rejects unknown attendance statuses', function () {
    $user = loginUser();
    $plan = tonightDinner($user->household_id);
    FamilyMember::create([
        'household_id' => $user->household_id,
        'user_id' => $user->id,
        'name' => 'Me',
    ]);

    Livewire::test(Today::class)
        ->call('setMyStatus', $plan->id, 'teleporting');

    expect($plan->attendees()->count())->toBe(0);
});

it('shows leftover reminder for unconsumed save_leftovers in past 3 days', function () {
    $user = loginUser();
    $recipe = Recipe::create(['household_id' => $user->household_id, 'name' => 'Chili', 'servings' => 6]);
    MealPlan::create([
        'household_id' => $user->household_id,
        'date' => CarbonImmutable::yesterday()->toDateString(),
        'slot' => 'dinner',
        'recipe_id' => $recipe->id,
        'save_leftovers' => true,
        'leftover_servings' => 2,
    ]);

    Livewire::test(Today::class)
        ->assertSee('Use it up')
        ->assertSee('Chili');
});

it('shows todos due today and overdue, and toggles them', function () {
    $user = loginUser();
    $list = TodoList::create(['household_id' => $user->household_id, 'name' => 'Chores']);
    $today = TodoItem::create(['todo_list_id' => $list->id, 'title' => 'Today task', 'due_date' => CarbonImmutable::today()->toDateString()]);
    $overdue = TodoItem::create(['todo_list_id' => $list->id, 'title' => 'Past task', 'due_date' => CarbonImmutable::today()->subDays(2)->toDateString()]);
    TodoItem::create(['todo_list_id' => $list->id, 'title' => 'Future task', 'due_date' => CarbonImmutable::today()->addDays(3)->toDateString()]);

    Livewire::test(Today::class)
        ->assertSee('Today task')
        ->assertSee('Past task')
        ->assertDontSee('Future task')
        ->call('toggleTodo', $today->id);

    expect($today->fresh()->completed_at)->not->toBeNull();
});

it('shows undated todos assigned to me', function () {
    $user = loginUser();
    $member = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Me', 'user_id' => $user->id]);
    $list = TodoList::create(['household_id' => $user->household_id, 'name' => 'Mine']);
    $mine = TodoItem::create(['todo_list_id' => $list->id, 'title' => 'Assigned to me']);
    $mine->assignees()->sync([$member->id]);
    TodoItem::create(['todo_list_id' => $list->id, 'title' => 'Unassigned and undated']);

    Livewire::test(Today::class)
        ->assertSee('Assigned to me')
        ->assertDontSee('Unassigned and undated');
});
