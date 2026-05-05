<?php

use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\User;

it('rejects apple endpoint without identity token', function () {
    $this->postJson('/api/auth/apple', [])->assertStatus(422);
});

it('rejects apple endpoint with malformed token', function () {
    $this->postJson('/api/auth/apple', ['identity_token' => 'not-a-jwt'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('identity_token');
});

it('provisions user, household, and family member on first apple login', function () {
    $token = appleJwt('apple-sub-123');

    $response = $this->postJson('/api/auth/apple', [
        'identity_token' => $token,
        'name' => 'Sheldon',
        'email' => 'sheldon@example.test',
        'device_name' => 'iPhone',
    ])->assertOk()->assertJsonStructure(['token', 'user']);

    $user = User::where('apple_sub', 'apple-sub-123')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Sheldon')
        ->and($user->email)->toBe('sheldon@example.test')
        ->and($user->household_id)->not->toBeNull()
        ->and($user->familyMember)->not->toBeNull();
    expect($response->json('token'))->toBeString();
});

it('captures timezone from the apple endpoint when provided and not already set', function () {
    $this->postJson('/api/auth/apple', [
        'identity_token' => appleJwt('tz-sub'),
        'timezone' => 'America/Los_Angeles',
    ])->assertOk();

    expect(\App\Models\User::where('apple_sub', 'tz-sub')->first()->timezone)
        ->toBe('America/Los_Angeles');
});

it('does not overwrite an existing timezone via the apple endpoint', function () {
    $h = \App\Models\Household::create(['name' => 'H']);
    $u = \App\Models\User::create([
        'household_id' => $h->id,
        'apple_sub' => 'tz-sub-2',
        'name' => 'X',
        'email' => 'x@example.test',
        'timezone' => 'Europe/Berlin',
    ]);

    $this->postJson('/api/auth/apple', [
        'identity_token' => appleJwt('tz-sub-2'),
        'timezone' => 'America/Los_Angeles',
    ])->assertOk();

    expect($u->fresh()->timezone)->toBe('Europe/Berlin');
});

it('rejects an invalid timezone on the apple endpoint', function () {
    $this->postJson('/api/auth/apple', [
        'identity_function' => appleJwt('x'),
        'identity_token' => appleJwt('x'),
        'timezone' => 'Mars/Olympus_Mons',
    ])->assertStatus(422);
});

it('reuses an existing user on repeat apple login', function () {
    $household = Household::create(['name' => 'Existing']);
    $user = User::create([
        'household_id' => $household->id,
        'apple_sub' => 'sub-xyz',
        'name' => 'Original',
        'email' => 'original@example.test',
    ]);
    FamilyMember::create([
        'household_id' => $household->id,
        'user_id' => $user->id,
        'name' => 'Original',
    ]);

    $this->postJson('/api/auth/apple', ['identity_token' => appleJwt('sub-xyz')])->assertOk();

    expect(User::where('apple_sub', 'sub-xyz')->count())->toBe(1)
        ->and(FamilyMember::where('user_id', $user->id)->count())->toBe(1);
});

it('devToken returns 404 outside local environment', function () {
    app()->detectEnvironment(fn() => 'production');
    $this->postJson('/api/auth/dev-token', ['email' => 'x@example.test'])->assertStatus(404);
});

it('devToken returns a token for known email in local env', function () {
    app()->detectEnvironment(fn() => 'local');
    $household = Household::create(['name' => 'H']);
    User::create([
        'household_id' => $household->id,
        'name' => 'Dev',
        'email' => 'dev@example.test',
    ]);

    $this->postJson('/api/auth/dev-token', ['email' => 'dev@example.test'])
        ->assertOk()
        ->assertJsonStructure(['token', 'user']);
});

it('devToken 404s for unknown email', function () {
    app()->detectEnvironment(fn() => 'local');
    $this->postJson('/api/auth/dev-token', ['email' => 'nope@example.test'])->assertStatus(404);
});

it('me returns the authenticated user with relations', function () {
    $user = loginApiUser();

    $this->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonStructure(['id', 'household']);
});

it('logout invalidates the current token', function () {
    loginApiUser();
    $this->postJson('/api/logout')->assertOk()->assertJson(['ok' => true]);
});

it('me requires authentication', function () {
    $this->getJson('/api/me')->assertStatus(401);
});
