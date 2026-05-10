<?php

use App\Livewire\MemberProfile;
use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\User;
use Livewire\Livewire;

it('toggles default attendance for a single day/slot', function () {
    $user = loginUser();
    $self = FamilyMember::create([
        'household_id' => $user->household_id,
        'user_id' => $user->id,
        'name' => 'Me',
        'color' => '#111111',
    ]);

    expect($self->attendsByDefault('fri', 'breakfast'))->toBeTrue();

    Livewire::test(MemberProfile::class, ['member' => $self])
        ->call('toggleAttendance', 'fri', 'breakfast');

    expect($self->fresh()->attendsByDefault('fri', 'breakfast'))->toBeFalse()
        ->and($self->fresh()->attendsByDefault('fri', 'lunch'))->toBeTrue();
});

it('skips an entire day at once', function () {
    $user = loginUser();
    $self = FamilyMember::create([
        'household_id' => $user->household_id,
        'user_id' => $user->id,
        'name' => 'Me',
        'color' => '#111111',
    ]);

    Livewire::test(MemberProfile::class, ['member' => $self])
        ->call('setDayAttendance', 'wed', false);

    $fresh = $self->fresh();
    foreach (MemberProfile::SLOTS as $slot) {
        expect($fresh->attendsByDefault('wed', $slot))->toBeFalse();
    }
});

it('skips a single slot across the week', function () {
    $user = loginUser();
    $self = FamilyMember::create([
        'household_id' => $user->household_id,
        'user_id' => $user->id,
        'name' => 'Me',
        'color' => '#111111',
    ]);

    Livewire::test(MemberProfile::class, ['member' => $self])
        ->call('setSlotAttendance', 'breakfast', false);

    $fresh = $self->fresh();
    foreach (MemberProfile::DAYS as $day) {
        expect($fresh->attendsByDefault($day, 'breakfast'))->toBeFalse();
        expect($fresh->attendsByDefault($day, 'lunch'))->toBeTrue();
    }
});

it('guests default to not attending any meals', function () {
    $user = loginUser();
    $guest = FamilyMember::create([
        'household_id' => $user->household_id,
        'name' => 'Guesty',
        'color' => '#222222',
        'is_guest' => true,
    ]);

    foreach (MemberProfile::DAYS as $day) {
        foreach (MemberProfile::SLOTS as $slot) {
            expect($guest->attendsByDefault($day, $slot))->toBeFalse();
        }
    }
});

it('admin can edit attendance for any member', function () {
    $household = Household::create(['name' => 'House']);
    $admin = loginUser($household);
    $household->users()->updateExistingPivot($admin->id, ['role' => 'admin']);

    $other = FamilyMember::create([
        'household_id' => $household->id,
        'name' => 'Other',
        'color' => '#222222',
    ]);

    Livewire::test(MemberProfile::class, ['member' => $other])
        ->call('toggleAttendance', 'mon', 'dinner');

    expect($other->fresh()->attendsByDefault('mon', 'dinner'))->toBeFalse();
});

it('non-admin cannot toggle attendance for another member', function () {
    $household = Household::create(['name' => 'House']);
    $admin = User::create(['household_id' => $household->id, 'name' => 'Admin', 'email' => 'a-'.uniqid().'@x.test']);
    $admin->households()->syncWithoutDetaching([$household->id => ['role' => 'admin']]);

    loginUser($household);

    $other = FamilyMember::create([
        'household_id' => $household->id,
        'name' => 'Other',
        'color' => '#222222',
    ]);

    Livewire::test(MemberProfile::class, ['member' => $other])
        ->call('toggleAttendance', 'mon', 'dinner')
        ->assertStatus(403);

    expect($other->fresh()->attendsByDefault('mon', 'dinner'))->toBeTrue();
});
