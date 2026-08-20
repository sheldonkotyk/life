<?php

use App\Models\FamilyMember;
use App\Models\FamilyMemberUnavailability;
use App\Models\Household;
use App\Models\MealPlan;
use App\Models\Recipe;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-08-19 15:00:00 UTC');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('returns a seven day window starting from the requested day', function () {
    loginApiUser();

    $response = $this->getJson('/api/planner?week_start=2026-08-17')->assertOk();

    expect($response->json('week_start'))->toBe('2026-08-17')
        ->and($response->json('days'))->toHaveCount(7)
        ->and($response->json('days.6'))->toBe('2026-08-23')
        ->and($response->json('today'))->toBe('2026-08-19');
});

it('groups plans by date and slot', function () {
    $user = loginApiUser();
    $recipe = Recipe::create(['household_id' => $user->household_id, 'name' => 'Soup', 'servings' => 4]);
    MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-08-19',
        'slot' => 'dinner',
        'recipe_id' => $recipe->id,
    ]);

    $response = $this->getJson('/api/planner?week_start=2026-08-17')->assertOk();

    expect($response->json('plans.2026-08-19|dinner.0.display_name'))->toBe('Soup');
});

it('assumes members attend and guests do not', function () {
    $user = loginApiUser();
    $member = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Member']);
    $guest = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Guest', 'is_guest' => true]);

    $defaults = $this->getJson('/api/planner?week_start=2026-08-17')
        ->assertOk()
        ->json('default_attendees.2026-08-19|dinner');

    expect($defaults)->toContain($member->id)->not->toContain($guest->id);
});

it('marks a member away for a meal and drops them from that plan', function () {
    $user = loginApiUser();
    $member = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Member']);
    $plan = MealPlan::create(['household_id' => $user->household_id, 'date' => '2026-08-19', 'slot' => 'dinner']);
    $plan->attendees()->sync([$member->id]);

    $this->postJson('/api/planner/attendance', [
        'family_member_id' => $member->id,
        'attending' => false,
        'slots' => [['date' => '2026-08-19', 'slot' => 'dinner']],
    ])->assertOk();

    expect(FamilyMemberUnavailability::where('family_member_id', $member->id)->count())->toBe(1)
        ->and($plan->fresh()->attendees()->count())->toBe(0);
});

it('records a guest as coming by writing the same row', function () {
    $user = loginApiUser();
    $guest = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Guest', 'is_guest' => true]);

    $this->postJson('/api/planner/attendance', [
        'family_member_id' => $guest->id,
        'attending' => true,
        'slots' => [['date' => '2026-08-19', 'slot' => 'dinner']],
    ])->assertOk();

    expect(FamilyMemberUnavailability::where('family_member_id', $guest->id)->count())->toBe(1);

    $defaults = $this->getJson('/api/planner?week_start=2026-08-17')
        ->assertOk()
        ->json('default_attendees.2026-08-19|dinner');

    expect($defaults)->toContain($guest->id);
});

it('accepts a whole row of slots in one call', function () {
    $user = loginApiUser();
    $member = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Member']);

    $this->postJson('/api/planner/attendance', [
        'family_member_id' => $member->id,
        'attending' => false,
        'slots' => [
            ['date' => '2026-08-19', 'slot' => 'breakfast'],
            ['date' => '2026-08-19', 'slot' => 'lunch'],
            ['date' => '2026-08-19', 'slot' => 'dinner'],
        ],
    ])->assertOk();

    expect(FamilyMemberUnavailability::where('family_member_id', $member->id)->count())->toBe(3);
});

it('refuses to set attendance for another household', function () {
    loginApiUser();
    $foreign = FamilyMember::create([
        'household_id' => Household::create(['name' => 'X'])->id,
        'name' => 'Theirs',
    ]);

    $this->postJson('/api/planner/attendance', [
        'family_member_id' => $foreign->id,
        'attending' => false,
        'slots' => [['date' => '2026-08-19', 'slot' => 'dinner']],
    ])->assertStatus(404);
});

it('offers leftovers saved in the days around the week', function () {
    $user = loginApiUser();
    $recipe = Recipe::create(['household_id' => $user->household_id, 'name' => 'Stew', 'servings' => 4]);
    MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-08-16',
        'slot' => 'dinner',
        'recipe_id' => $recipe->id,
        'save_leftovers' => true,
    ]);

    $leftovers = $this->getJson('/api/planner?week_start=2026-08-17')->assertOk()->json('available_leftovers');

    expect($leftovers)->toHaveCount(1)->and($leftovers[0]['name'])->toBe('Stew');
});

it('hides an already eaten leftover', function () {
    $user = loginApiUser();
    $recipe = Recipe::create(['household_id' => $user->household_id, 'name' => 'Stew', 'servings' => 4]);
    $source = MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-08-16',
        'slot' => 'dinner',
        'recipe_id' => $recipe->id,
        'save_leftovers' => true,
    ]);
    $eater = MealPlan::create(['household_id' => $user->household_id, 'date' => '2026-08-18', 'slot' => 'lunch']);
    $eater->leftoverSources()->attach($source->id);

    expect($this->getJson('/api/planner?week_start=2026-08-17')->assertOk()->json('available_leftovers'))
        ->toBe([]);
});
