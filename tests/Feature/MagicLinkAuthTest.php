<?php

use App\Mail\MagicLoginLink;
use App\Models\Household;
use App\Models\MagicLoginToken;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    Mail::fake();
    RateLimiter::clear('magic-link:' . sha1('new@example.test|127.0.0.1'));
});

it('shows the magic-link email field on login', function () {
    $this->get('/login')->assertOk()->assertSee('Email me a sign-in link');
});

it('requests a magic link and stores a hashed token', function () {
    $this->startSession();
    $this->post('/auth/magic', [
        '_token' => csrf_token(),
        'email' => 'new@example.test',
    ])->assertRedirect();

    $record = MagicLoginToken::where('email', 'new@example.test')->first();
    expect($record)->not->toBeNull()
        ->and($record->token_hash)->toHaveLength(64)
        ->and($record->expires_at->isFuture())->toBeTrue();

    Mail::assertSent(MagicLoginLink::class, fn ($m) => $m->hasTo('new@example.test'));
});

it('validates the email on request', function () {
    $this->startSession();
    $this->post('/auth/magic', [
        '_token' => csrf_token(),
        'email' => 'not-an-email',
    ])->assertSessionHasErrors('email');
});

it('signs in a brand-new user via magic link and provisions a household', function () {
    $token = 'tok_' . str_repeat('a', 44);
    MagicLoginToken::create([
        'email' => 'fresh@example.test',
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->get('/auth/magic/' . $token)->assertRedirect('/');

    $user = User::where('email', 'fresh@example.test')->first();
    expect($user)->not->toBeNull()
        ->and($user->household_id)->not->toBeNull()
        ->and($user->familyMember)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(auth()->id())->toBe($user->id);

    expect(MagicLoginToken::find(MagicLoginToken::first()->id)->used_at)->not->toBeNull();
});

it('signs in an existing user via magic link without creating a new household', function () {
    $h = Household::create(['name' => 'H']);
    $existing = User::create([
        'household_id' => $h->id,
        'name' => 'Ex',
        'email' => 'ex@example.test',
    ]);

    $token = 'tok_' . str_repeat('b', 44);
    MagicLoginToken::create([
        'email' => 'ex@example.test',
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->get('/auth/magic/' . $token)->assertRedirect('/');

    expect(auth()->id())->toBe($existing->id)
        ->and(User::where('email', 'ex@example.test')->count())->toBe(1)
        ->and(Household::count())->toBe(1);
});

it('rejects an expired magic-link token', function () {
    $token = 'tok_' . str_repeat('c', 44);
    MagicLoginToken::create([
        'email' => 'late@example.test',
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->subMinute(),
    ]);

    $this->get('/auth/magic/' . $token)->assertRedirect('/login');
    expect(auth()->check())->toBeFalse();
});

it('rejects a reused magic-link token', function () {
    $token = 'tok_' . str_repeat('d', 44);
    MagicLoginToken::create([
        'email' => 'used@example.test',
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->addMinutes(15),
        'used_at' => now(),
    ]);

    $this->get('/auth/magic/' . $token)->assertRedirect('/login');
    expect(auth()->check())->toBeFalse();
});

it('rejects an unknown magic-link token', function () {
    $this->get('/auth/magic/totally-bogus-token')->assertRedirect('/login');
    expect(auth()->check())->toBeFalse();
});

it('signs in via the emailed code', function () {
    $code = '123456';
    MagicLoginToken::create([
        'email' => 'codeuser@example.test',
        'token_hash' => hash('sha256', 'unused-token-' . str_repeat('x', 36)),
        'code_hash' => hash('sha256', $code),
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->withSession(['magic_pending_email' => 'codeuser@example.test'])
        ->post('/auth/magic/verify', ['code' => $code])
        ->assertRedirect('/');

    expect(auth()->check())->toBeTrue()
        ->and(auth()->user()->email)->toBe('codeuser@example.test');
});

it('rejects a wrong magic code', function () {
    MagicLoginToken::create([
        'email' => 'codeuser@example.test',
        'token_hash' => hash('sha256', 'tk-' . str_repeat('y', 45)),
        'code_hash' => hash('sha256', '123456'),
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->withSession(['magic_pending_email' => 'codeuser@example.test'])
        ->post('/auth/magic/verify', ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect(auth()->check())->toBeFalse();
});

it('attaches the pending invite household when verifying a magic link', function () {
    $h = Household::create(['name' => 'Invited Household', 'invite_code' => 'INV12345']);

    $this->startSession();
    $this->post('/login/invite', ['_token' => csrf_token(), 'invite_code' => 'INV12345']);

    $token = 'tok_' . str_repeat('e', 44);
    MagicLoginToken::create([
        'email' => 'invitee@example.test',
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->get('/auth/magic/' . $token)->assertRedirect('/');

    $user = User::where('email', 'invitee@example.test')->first();
    expect($user->household_id)->toBe($h->id);
});
