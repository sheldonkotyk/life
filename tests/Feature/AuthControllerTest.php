<?php

use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery\MockInterface;

it('renders the login page with apple disabled when no client id', function () {
    config(['services.apple.client_id' => null]);
    $this->get('/login')->assertOk()->assertSee('Life');
});

it('renders the login page with apple enabled when configured', function () {
    config(['services.apple.client_id' => 'com.example.app']);
    $this->get('/login')->assertOk()->assertSee('Sign in with Apple');
});

it('exposes dev users on the login page in local env', function () {
    app()->detectEnvironment(fn() => 'local');
    $h = Household::create(['name' => 'H']);
    User::create(['household_id' => $h->id, 'name' => 'Devo', 'email' => 'devo@example.test']);

    $this->get('/login')->assertOk()->assertSee('Devo');
});

it('redirects to apple via socialite', function () {
    Socialite::shouldReceive('driver->scopes->redirect')
        ->andReturn(redirect('https://appleid.apple.com/auth/authorize'));

    $this->get('/auth/apple/redirect')->assertRedirect('https://appleid.apple.com/auth/authorize');
});

it('provisions a new user on apple callback', function () {
    $apple = Mockery::mock(SocialiteUser::class);
    $apple->shouldReceive('getId')->andReturn('apple-sub-cb');
    $apple->shouldReceive('getEmail')->andReturn('cb@example.test');
    $apple->shouldReceive('getName')->andReturn('CB User');
    $apple->shouldReceive('getAvatar')->andReturn(null);
    Socialite::shouldReceive('driver->user')->andReturn($apple);

    $this->get('/auth/apple/callback')->assertRedirect('/');

    $user = User::where('apple_sub', 'apple-sub-cb')->first();
    expect($user)->not->toBeNull()
        ->and($user->household_id)->not->toBeNull()
        ->and($user->familyMember)->not->toBeNull()
        ->and(auth()->id())->toBe($user->id);
});

it('reuses an existing user on apple callback', function () {
    $h = Household::create(['name' => 'H']);
    $existing = User::create([
        'household_id' => $h->id,
        'apple_sub' => 'sub-existing',
        'name' => 'Existing',
        'email' => 'existing@example.test',
    ]);
    FamilyMember::create([
        'household_id' => $h->id,
        'user_id' => $existing->id,
        'name' => 'Existing',
    ]);

    $apple = Mockery::mock(SocialiteUser::class);
    $apple->shouldReceive('getId')->andReturn('sub-existing');
    $apple->shouldReceive('getEmail')->andReturn(null);
    $apple->shouldReceive('getName')->andReturn(null);
    $apple->shouldReceive('getAvatar')->andReturn(null);
    Socialite::shouldReceive('driver->user')->andReturn($apple);

    $this->get('/auth/apple/callback')->assertRedirect('/');

    expect(User::where('apple_sub', 'sub-existing')->count())->toBe(1)
        ->and(auth()->id())->toBe($existing->id);
});

it('logs out and redirects to login', function () {
    loginUser();
    $this->post('/logout')->assertRedirect('/login');
    expect(auth()->check())->toBeFalse();
});

it('dev-login logs in the user in local env', function () {
    app()->detectEnvironment(fn() => 'local');
    $h = Household::create(['name' => 'H']);
    $user = User::create(['household_id' => $h->id, 'name' => 'D', 'email' => 'd@example.test']);

    $this->startSession();
    $this->post("/dev-login/{$user->id}", ['_token' => csrf_token()])->assertRedirect('/');
    expect(auth()->id())->toBe($user->id);
});

it('dev-login 404s outside local env', function () {
    app()->detectEnvironment(fn() => 'production');
    $h = Household::create(['name' => 'H']);
    $user = User::create(['household_id' => $h->id, 'name' => 'D', 'email' => 'd@example.test']);

    $this->startSession();
    $this->post("/dev-login/{$user->id}", ['_token' => csrf_token()])->assertStatus(404);
});
