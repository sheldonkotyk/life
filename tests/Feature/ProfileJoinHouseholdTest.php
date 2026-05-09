<?php

use App\Livewire\Profile;
use App\Models\Household;
use Livewire\Livewire;

it('joins a household via invite code from profile', function () {
    $user = loginUser();
    $other = Household::create(['name' => 'Other House', 'invite_code' => 'JOINME12']);

    Livewire::test(Profile::class)
        ->set('joinCode', 'joinme12')
        ->call('joinHousehold');

    expect($user->fresh()->household_id)->toBe($other->id)
        ->and($user->fresh()->households()->where('households.id', $other->id)->exists())->toBeTrue();
});

it('rejects an unknown invite code', function () {
    loginUser();

    Livewire::test(Profile::class)
        ->set('joinCode', 'NOPE0000')
        ->call('joinHousehold')
        ->assertHasErrors('joinCode');
});

it('rejects joining the household the user is already on', function () {
    $user = loginUser();
    $user->household->update(['invite_code' => 'SAME1234']);

    Livewire::test(Profile::class)
        ->set('joinCode', 'SAME1234')
        ->call('joinHousehold')
        ->assertHasErrors('joinCode');
});
