<?php

use App\Livewire\Profile;
use App\Models\Household;
use Livewire\Livewire;

it('creates a new household and makes the user its admin', function () {
    $user = loginUser();

    Livewire::test(Profile::class)
        ->set('newHouseholdName', 'Lake House')
        ->call('createHousehold')
        ->assertHasNoErrors();

    $created = Household::where('name', 'Lake House')->first();

    expect($created)->not->toBeNull()
        ->and($user->fresh()->household_id)->toBe($created->id)
        ->and($user->fresh()->isAdminOf($created))->toBeTrue();
});

it('requires a name when creating a household', function () {
    loginUser();

    Livewire::test(Profile::class)
        ->set('newHouseholdName', '')
        ->call('createHousehold')
        ->assertHasErrors(['newHouseholdName' => 'required']);
});
