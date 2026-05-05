<?php

use App\Livewire\Profile;
use Livewire\Livewire;

it('captures browser timezone when none is stored', function () {
    $user = loginUser();
    expect($user->timezone)->toBeNull();

    $this->postJson('/me/timezone', ['timezone' => 'America/Los_Angeles'])
        ->assertOk()
        ->assertJson(['timezone' => 'America/Los_Angeles']);

    expect($user->fresh()->timezone)->toBe('America/Los_Angeles');
});

it('does not overwrite a timezone the user has explicitly set', function () {
    $user = loginUser();
    $user->update(['timezone' => 'Europe/Berlin']);

    $this->postJson('/me/timezone', ['timezone' => 'America/Los_Angeles'])
        ->assertOk()
        ->assertJson(['timezone' => 'Europe/Berlin']);

    expect($user->fresh()->timezone)->toBe('Europe/Berlin');
});

it('rejects invalid timezone identifiers', function () {
    loginUser();
    $this->postJson('/me/timezone', ['timezone' => 'Mars/Olympus_Mons'])->assertStatus(422);
});

it('requires authentication for the timezone endpoint', function () {
    $this->postJson('/me/timezone', ['timezone' => 'UTC'])->assertStatus(401);
});

it('Profile screen mounts with the user\'s current values', function () {
    $user = loginUser();
    $user->update(['timezone' => 'America/New_York', 'name' => 'Sheldon']);

    Livewire::test(Profile::class)
        ->assertSet('name', 'Sheldon')
        ->assertSet('timezone', 'America/New_York');
});

it('Profile screen saves changes', function () {
    $user = loginUser();

    Livewire::test(Profile::class)
        ->set('name', 'Updated')
        ->set('timezone', 'Asia/Tokyo')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->name)->toBe('Updated')
        ->and($user->fresh()->timezone)->toBe('Asia/Tokyo');
});

it('Profile screen rejects invalid timezones', function () {
    loginUser();

    Livewire::test(Profile::class)
        ->set('timezone', 'Not/Real')
        ->call('save')
        ->assertHasErrors(['timezone']);
});
