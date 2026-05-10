<?php

use App\Livewire\Family;
use App\Livewire\MemberProfile;
use App\Models\FamilyMember;
use App\Models\FoodPreference;
use Livewire\Livewire;

it('creates a family member via the form', function () {
    $user = loginUser();

    Livewire::test(Family::class)
        ->set('name', 'Newbie')
        ->set('color', '#abcdef')
        ->call('save')
        ->assertHasNoErrors();

    expect(FamilyMember::where('household_id', $user->household_id)->where('name', 'Newbie')->exists())->toBeTrue();
});

it('validates required name on save', function () {
    loginUser();

    Livewire::test(Family::class)
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

it('edits an existing member via the profile page', function () {
    $user = loginUser();
    $member = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Old', 'color' => '#000000']);

    Livewire::test(MemberProfile::class, ['member' => $member])
        ->assertSet('name', 'Old')
        ->set('name', 'New')
        ->call('save');

    expect($member->fresh()->name)->toBe('New');
});

it('deletes a member', function () {
    $user = loginUser();
    $member = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Bye']);

    Livewire::test(Family::class)->call('delete', $member->id);

    expect(FamilyMember::find($member->id))->toBeNull();
});

it('adds a food preference', function () {
    $user = loginUser();
    $member = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Eater']);

    Livewire::test(Family::class)
        ->call('startAddingPreference', $member->id)
        ->set('prefFood', 'shellfish')
        ->set('prefType', 'allergy')
        ->call('addPreference', $member->id);

    expect($member->preferences()->count())->toBe(1)
        ->and($member->preferences()->first()->food)->toBe('shellfish');
});

it('removes a food preference', function () {
    $user = loginUser();
    $member = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Eater']);
    $pref = FoodPreference::create([
        'family_member_id' => $member->id,
        'food' => 'kale',
        'type' => 'dislike',
    ]);

    Livewire::test(Family::class)->call('removePreference', $pref->id);

    expect(FoodPreference::find($pref->id))->toBeNull();
});
