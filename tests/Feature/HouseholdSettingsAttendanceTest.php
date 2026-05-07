<?php

use App\Livewire\HouseholdSettings;
use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\User;
use Livewire\Livewire;

function makeAdmin(User $user, Household $household): void
{
    $household->users()->updateExistingPivot($user->id, ['role' => 'admin']);
}

it('toggles default attendance for a single day/slot', function () {
    $user = loginUser();
    $self = FamilyMember::create([
        'household_id' => $user->household_id,
        'user_id' => $user->id,
        'name' => 'Me',
        'color' => '#111111',
    ]);

    expect($self->attendsByDefault('fri', 'breakfast'))->toBeTrue();

    Livewire::test(HouseholdSettings::class)
        ->set('attendanceMemberId', $self->id)
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

    Livewire::test(HouseholdSettings::class)
        ->set('attendanceMemberId', $self->id)
        ->call('setDayAttendance', 'wed', false);

    $fresh = $self->fresh();
    foreach (HouseholdSettings::SLOTS as $slot) {
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

    Livewire::test(HouseholdSettings::class)
        ->set('attendanceMemberId', $self->id)
        ->call('setSlotAttendance', 'breakfast', false);

    $fresh = $self->fresh();
    foreach (HouseholdSettings::DAYS as $day) {
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

    foreach (HouseholdSettings::DAYS as $day) {
        foreach (HouseholdSettings::SLOTS as $slot) {
            expect($guest->attendsByDefault($day, $slot))->toBeFalse();
        }
    }
});

it('admins see every member including guests in the dropdown', function () {
    $household = Household::create(['name' => 'House']);
    $admin = loginUser($household);
    makeAdmin($admin, $household);

    $kid = FamilyMember::create(['household_id' => $household->id, 'name' => 'Kid', 'color' => '#111111']);
    $guest = FamilyMember::create(['household_id' => $household->id, 'name' => 'Guesty', 'color' => '#222222', 'is_guest' => true]);

    Livewire::test(HouseholdSettings::class)
        ->assertViewHas('attendanceMembers', fn ($members) => $members->pluck('id')->contains($kid->id)
            && $members->pluck('id')->contains($guest->id));
});

it('non-admins only see themselves in the dropdown', function () {
    $household = Household::create(['name' => 'House']);
    $admin = User::create(['household_id' => $household->id, 'name' => 'Admin', 'email' => 'a-'.uniqid().'@x.test']);
    $admin->households()->syncWithoutDetaching([$household->id => ['role' => 'admin']]);

    $user = loginUser($household);

    $self = FamilyMember::create(['household_id' => $household->id, 'user_id' => $user->id, 'name' => 'Me', 'color' => '#111111']);
    FamilyMember::create(['household_id' => $household->id, 'name' => 'Other', 'color' => '#222222']);
    FamilyMember::create(['household_id' => $household->id, 'name' => 'Guesty', 'color' => '#333333', 'is_guest' => true]);

    Livewire::test(HouseholdSettings::class)
        ->assertViewHas('attendanceMembers', fn ($members) => $members->pluck('id')->all() === [$self->id]);
});

it('non-admin cannot toggle attendance for another member by switching the dropdown', function () {
    $household = Household::create(['name' => 'House']);
    $admin = User::create(['household_id' => $household->id, 'name' => 'Admin', 'email' => 'a-'.uniqid().'@x.test']);
    $admin->households()->syncWithoutDetaching([$household->id => ['role' => 'admin']]);

    loginUser($household);

    $other = FamilyMember::create(['household_id' => $household->id, 'name' => 'Other', 'color' => '#222222']);

    Livewire::test(HouseholdSettings::class)
        ->set('attendanceMemberId', $other->id)
        ->call('toggleAttendance', 'mon', 'dinner');

    expect($other->fresh()->attendsByDefault('mon', 'dinner'))->toBeTrue();
});
