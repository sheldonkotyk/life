<?php

use App\Livewire\Profile;
use App\Models\FamilyMember;
use Livewire\Livewire;

it('toggles default attendance for the linked family member', function () {
    $user = loginUser();
    $member = FamilyMember::create([
        'household_id' => $user->household_id,
        'user_id' => $user->id,
        'name' => $user->name,
        'color' => '#6366f1',
    ]);

    expect($member->attendsByDefault('mon', 'breakfast'))->toBeTrue();

    Livewire::test(Profile::class)
        ->call('toggleAttendance', 'mon', 'breakfast');

    expect($member->fresh()->attendsByDefault('mon', 'breakfast'))->toBeFalse();
});

it('sets all slots for a day', function () {
    $user = loginUser();
    $member = FamilyMember::create([
        'household_id' => $user->household_id,
        'user_id' => $user->id,
        'name' => $user->name,
        'color' => '#6366f1',
    ]);

    Livewire::test(Profile::class)
        ->call('setDayAttendance', 'fri', false);

    $fresh = $member->fresh();
    foreach (['breakfast', 'lunch', 'dinner'] as $slot) {
        expect($fresh->attendsByDefault('fri', $slot))->toBeFalse();
    }
});
