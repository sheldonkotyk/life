<?php

use App\Models\FamilyMember;
use App\Models\FamilyMemberUnavailability;
use App\Models\MealPlan;
use App\Models\Recipe;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Pin "now" so weekStart = Friday 2026-05-08 and the rendered date
    // labels match the assertions below.
    Carbon::setTestNow('2026-05-08 12:00:00');
    CarbonImmutable::setTestNow('2026-05-08 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

/** Returns JS to click an element by its `wire:click` attribute prefix. */
function jsClickWire(string $wireClickPrefix): string
{
    $escaped = addslashes($wireClickPrefix);

    return "document.querySelector('[wire\\\\:click^=\"{$escaped}\"]').click()";
}

/** Returns JS to click a button by exact visible text. */
function jsClickByText(string $text): string
{
    $escaped = addslashes($text);

    return "document.querySelectorAll('button').forEach(b => { if (b.textContent.trim() === '{$escaped}') b.click(); })";
}

function setupPlannerHousehold(): array
{
    $user = loginUser();
    $ava = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Ava']);
    $ben = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Ben']);

    return [$user, $ava, $ben];
}

it('shifts forward and back through weeks with Prev / Next / Today', function () {
    setupPlannerHousehold();

    $page = visit('/meal-plan');

    $page->assertSee('May 8 – May 14, 2026')->assertNoJavaScriptErrors();

    $page->script(jsClickWire('shiftWeek(1)'));
    $page->assertSee('May 15 – May 21, 2026');

    $page->script(jsClickWire('shiftWeek(-1)'));
    $page->assertSee('May 8 – May 14, 2026');

    $page->script(jsClickWire('shiftWeek(1)'));
    $page->assertSee('May 15 – May 21, 2026');
    $page->script(jsClickWire('shiftWeek(1)'));
    $page->assertSee('May 22 – May 28, 2026');

    $page->script(jsClickWire('jumpToToday'));
    $page->assertSee('May 8 – May 14, 2026');
});

it('toggles between Plan and Attendance modes', function () {
    setupPlannerHousehold();

    $page = visit('/meal-plan');

    $page->assertSee("Plan meals and mark who's eating.")->assertNoJavaScriptErrors();

    $page->script(jsClickByText('Attendance'));
    $page->assertSee("Check the meals you'll be there for this week.");

    $page->script(jsClickByText('Plan'));
    $page->assertSee("Plan meals and mark who's eating.");
});

it('creates a new meal plan via the modal Save button', function () {
    [$user, $ava] = setupPlannerHousehold();
    $recipe = Recipe::create([
        'household_id' => $user->household_id,
        'name' => 'Spaghetti',
        'servings' => 4,
    ]);

    $page = visit('/meal-plan');

    $page->assertNoJavaScriptErrors();
    $page->script(jsClickWire('openSlot'));
    $page->assertSee('Friday, May 8');

    $page->select('selectedRecipeId', (string) $recipe->id)
        ->click('Save')
        ->assertDontSee('Friday, May 8');

    expect(MealPlan::count())->toBe(1);
    $plan = MealPlan::first();
    expect($plan->date->toDateString())->toBe('2026-05-08')
        ->and($plan->slot)->toBe('breakfast')
        ->and($plan->recipe_id)->toBe($recipe->id)
        ->and($plan->attendees->pluck('id')->all())->toContain($ava->id);
});

it('cancels the modal without persisting changes', function () {
    setupPlannerHousehold();

    $page = visit('/meal-plan');

    $page->assertNoJavaScriptErrors();
    $page->script(jsClickWire('openSlot'));
    $page->assertSee("Who's eating?")
        ->click('Cancel')
        ->assertDontSee("Who's eating?");

    expect(MealPlan::count())->toBe(0);
});

it('removes an existing meal plan via the Remove button', function () {
    [$user, $ava] = setupPlannerHousehold();
    $plan = MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-05-08',
        'slot' => 'breakfast',
        'custom_name' => 'Pancakes',
    ]);
    $plan->attendees()->attach($ava->id, ['status' => 'eating']);

    $page = visit('/meal-plan');

    $page->assertSee('Pancakes')->assertNoJavaScriptErrors();
    $page->script(jsClickWire("openSlot('2026-05-08', 'breakfast', {$plan->id}"));
    $page->assertSee('Remove');

    // wire:confirm pops a JS confirm — auto-accept it.
    $page->script('window.confirm = () => true');
    $page->click('Remove')->assertDontSee('Pancakes');

    expect(MealPlan::count())->toBe(0);
});

it('marks a member as not attending a slot via the attendance grid', function () {
    [$user, $ava] = setupPlannerHousehold();

    $page = visit('/meal-plan');

    $page->script(jsClickByText('Attendance'));
    $page->assertNoJavaScriptErrors();
    $page->script("document.querySelector('[wire\\\\:click^=\"setAttending(\\'2026-05-08\\', \\'breakfast\\'\"]').click()");
    usleep(500_000);
    $page->assertNoJavaScriptErrors();

    $memberId = $user->familyMember?->id ?? $ava->id;
    expect(FamilyMemberUnavailability::where('family_member_id', $memberId)
        ->where('date', '2026-05-08')
        ->where('slot', 'breakfast')
        ->exists())->toBeTrue();
});

it('skips a whole day with the row Skip button', function () {
    [$user, $ava] = setupPlannerHousehold();

    $page = visit('/meal-plan');

    $page->script(jsClickByText('Attendance'));
    $page->assertNoJavaScriptErrors();
    // Both mobile and desktop layouts contain the button; click them all —
    // setDayAttending is idempotent (firstOrCreate / delete by key).
    $page->script("document.querySelectorAll('[wire\\\\:click^=\"setDayAttending(\\'2026-05-08\\'\"]').forEach(e => e.click())");
    usleep(1_500_000);
    $page->assertNoJavaScriptErrors();

    $memberId = $user->familyMember?->id ?? $ava->id;
    expect(FamilyMemberUnavailability::where('family_member_id', $memberId)
        ->where('date', '2026-05-08')
        ->count())->toBe(3); // breakfast, lunch, dinner
});

it('skips an entire slot column with the column Skip all button', function () {
    [$user, $ava] = setupPlannerHousehold();

    $page = visit('/meal-plan');

    $page->script(jsClickByText('Attendance'));
    $page->assertNoJavaScriptErrors();
    $page->script("document.querySelector('[wire\\\\:click^=\"setSlotAttending(\\'breakfast\\'\"]').click()");
    usleep(500_000);
    $page->assertNoJavaScriptErrors();

    $memberId = $user->familyMember?->id ?? $ava->id;
    expect(FamilyMemberUnavailability::where('family_member_id', $memberId)
        ->where('slot', 'breakfast')
        ->count())->toBe(7); // every day in the week
});
