<?php

use App\Livewire\Availability;
use App\Models\FamilyMember;
use App\Models\FamilyMemberUnavailability;
use App\Models\MealPlan;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

it('mounts with the user\'s family member', function () {
    $user = loginUser();
    $member = FamilyMember::create([
        'household_id' => $user->household_id,
        'user_id' => $user->id,
        'name' => 'Mine',
    ]);

    Livewire::test(Availability::class)->assertSet('memberId', $member->id);
});

it('marks a slot as unavailable and detaches from existing meal plans', function () {
    $user = loginUser();
    $member = FamilyMember::create([
        'household_id' => $user->household_id,
        'user_id' => $user->id,
        'name' => 'Me',
    ]);
    $today = CarbonImmutable::today()->toDateString();
    $plan = MealPlan::create([
        'household_id' => $user->household_id,
        'date' => $today,
        'slot' => 'dinner',
    ]);
    $plan->attendees()->sync([$member->id]);

    Livewire::test(Availability::class)
        ->call('setAttending', $today, 'dinner', false);

    expect(FamilyMemberUnavailability::where('family_member_id', $member->id)->count())->toBe(1)
        ->and($plan->fresh()->attendees)->toHaveCount(0);
});

it('toggles a slot back to attending and removes the unavailability', function () {
    $user = loginUser();
    FamilyMember::create([
        'household_id' => $user->household_id,
        'user_id' => $user->id,
        'name' => 'Me',
    ]);
    $today = CarbonImmutable::today()->toDateString();

    Livewire::test(Availability::class)
        ->call('setAttending', $today, 'lunch', false)
        ->call('setAttending', $today, 'lunch', true);

    expect(FamilyMemberUnavailability::count())->toBe(0);
});

it('toggles a whole slot across the week back to attending', function () {
    $user = loginUser();
    FamilyMember::create([
        'household_id' => $user->household_id,
        'user_id' => $user->id,
        'name' => 'Me',
    ]);

    Livewire::test(Availability::class)
        ->call('setSlotAttending', 'breakfast', false)
        ->call('setSlotAttending', 'breakfast', true);

    expect(FamilyMemberUnavailability::count())->toBe(0);
});

it('toggles a whole day back to attending', function () {
    $user = loginUser();
    FamilyMember::create([
        'household_id' => $user->household_id,
        'user_id' => $user->id,
        'name' => 'Me',
    ]);
    $today = CarbonImmutable::today()->toDateString();

    Livewire::test(Availability::class)
        ->call('setDayAttending', $today, false)
        ->call('setDayAttending', $today, true);

    expect(FamilyMemberUnavailability::count())->toBe(0);
});

it('falls back to any household member when the user has none', function () {
    $user = loginUser();
    $other = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'OnlyOne']);

    Livewire::test(Availability::class)->assertSet('memberId', $other->id);
});

it('ignores unknown slots', function () {
    $user = loginUser();
    FamilyMember::create([
        'household_id' => $user->household_id,
        'user_id' => $user->id,
        'name' => 'Me',
    ]);

    Livewire::test(Availability::class)
        ->call('setAttending', CarbonImmutable::today()->toDateString(), 'brunch', false);

    expect(FamilyMemberUnavailability::count())->toBe(0);
});

it('marks a whole slot across the week as unavailable', function () {
    $user = loginUser();
    $member = FamilyMember::create([
        'household_id' => $user->household_id,
        'user_id' => $user->id,
        'name' => 'Me',
    ]);

    Livewire::test(Availability::class)->call('setSlotAttending', 'breakfast', false);

    expect(FamilyMemberUnavailability::where('family_member_id', $member->id)->where('slot', 'breakfast')->count())->toBe(7);
});

it('marks a whole day across all slots as unavailable', function () {
    $user = loginUser();
    $member = FamilyMember::create([
        'household_id' => $user->household_id,
        'user_id' => $user->id,
        'name' => 'Me',
    ]);
    $today = CarbonImmutable::today()->toDateString();

    Livewire::test(Availability::class)->call('setDayAttending', $today, false);

    expect(FamilyMemberUnavailability::where('family_member_id', $member->id)->whereDate('date', $today)->count())->toBe(3);
});
