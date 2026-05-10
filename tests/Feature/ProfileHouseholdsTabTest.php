<?php

use App\Livewire\Profile;
use App\Models\Household;
use App\Models\User;
use Livewire\Livewire;

it('switches to another household the user belongs to', function () {
    $user = loginUser();
    $other = Household::create(['name' => 'Second House']);
    $user->joinHousehold($other);

    $user->forceFill(['household_id' => $user->households()->orderBy('households.id')->first()->id])->save();
    $original = $user->fresh()->household_id;
    expect($original)->not->toBe($other->id);

    Livewire::test(Profile::class)
        ->call('switchHousehold', $other->id);

    expect($user->fresh()->household_id)->toBe($other->id);
});

it('refuses to switch to a household the user does not belong to', function () {
    loginUser();
    $foreign = Household::create(['name' => 'Stranger House']);

    Livewire::test(Profile::class)
        ->call('switchHousehold', $foreign->id)
        ->assertStatus(403);
});

it('leaves a non-active household and stays on the active one', function () {
    $user = loginUser();
    $other = Household::create(['name' => 'Spare House']);
    $user->joinHousehold($other);
    $user->forceFill(['household_id' => $user->households()->orderBy('households.id')->first()->id])->save();
    $activeBefore = $user->fresh()->household_id;

    Livewire::test(Profile::class)
        ->call('leaveHousehold', $other->id);

    $user->refresh();
    expect($user->household_id)->toBe($activeBefore)
        ->and($user->households()->where('households.id', $other->id)->exists())->toBeFalse();
});

it('falls back to another household when leaving the active one', function () {
    $user = loginUser();
    $active = $user->household;
    $other = Household::create(['name' => 'Backup House']);
    $user->joinHousehold($other);
    $user->forceFill(['household_id' => $active->id])->save();

    Livewire::test(Profile::class)
        ->call('leaveHousehold', $active->id);

    $user->refresh();
    expect($user->household_id)->toBe($other->id)
        ->and($user->households()->where('households.id', $active->id)->exists())->toBeFalse();
});

it('blocks leaving when user is the only admin and others remain', function () {
    $user = loginUser();
    $household = $user->household;
    $user->households()->updateExistingPivot($household->id, ['role' => 'admin']);

    $other = User::create([
        'household_id' => $household->id,
        'name' => 'Roomie',
        'email' => 'roomie-'.uniqid().'@example.test',
    ]);
    $other->households()->syncWithoutDetaching([$household->id => ['role' => 'member']]);

    Livewire::test(Profile::class)
        ->call('leaveHousehold', $household->id);

    expect($user->fresh()->households()->where('households.id', $household->id)->exists())->toBeTrue();
});
