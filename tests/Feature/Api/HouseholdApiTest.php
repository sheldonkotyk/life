<?php

use App\Models\Household;
use App\Models\User;

it('shows the authenticated household', function () {
    $user = loginApiUser();

    $this->getJson('/api/household')
        ->assertOk()
        ->assertJsonPath('id', $user->household_id);
});

it('updates the household name', function () {
    $user = loginApiUser();

    $this->patchJson('/api/household', ['name' => 'Renamed'])
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect($user->household->fresh()->name)->toBe('Renamed');
});

it('rejects empty name on update', function () {
    loginApiUser();
    $this->patchJson('/api/household', ['name' => ''])->assertStatus(422);
});

it('rotates the invite code to an 8-char uppercase string', function () {
    $user = loginApiUser();
    $original = $user->household->invite_code;

    $response = $this->postJson('/api/household/rotate-invite')->assertOk();
    $code = $response->json('invite_code');

    expect($code)->toBeString()
        ->and(strlen($code))->toBe(8)
        ->and($code)->toBe(strtoupper($code))
        ->and($code)->not->toBe($original);
});

it('joins another household via invite code', function () {
    $user = loginApiUser();
    $other = Household::create(['name' => 'Other', 'invite_code' => 'JOINME12']);

    $this->postJson('/api/household/join', ['invite_code' => 'joinme12'])
        ->assertOk()
        ->assertJson(['ok' => true, 'household_id' => $other->id]);

    expect($user->fresh()->household_id)->toBe($other->id);
});

it('returns 404 when joining with an invalid code', function () {
    loginApiUser();
    $this->postJson('/api/household/join', ['invite_code' => 'NOPE0000'])->assertStatus(404);
});
