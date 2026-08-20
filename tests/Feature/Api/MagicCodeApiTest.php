<?php

use App\Mail\MagicLoginLink;
use App\Models\Household;
use App\Models\MagicLoginToken;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

it('emails a sign-in code and stores a token row', function () {
    $this->postJson('/api/auth/magic/request', ['email' => 'Sheldon@Example.test'])
        ->assertOk()
        ->assertJson(['ok' => true, 'expires_in_minutes' => 15]);

    expect(MagicLoginToken::where('email', 'sheldon@example.test')->count())->toBe(1);
    Mail::assertSent(MagicLoginLink::class);
});

it('rejects a request without an email', function () {
    $this->postJson('/api/auth/magic/request', [])->assertStatus(422);
});

it('exchanges a valid code for an api token and provisions the account', function () {
    $this->postJson('/api/auth/magic/request', ['email' => 'new@example.test'])->assertOk();

    $code = null;
    Mail::assertSent(MagicLoginLink::class, function (MagicLoginLink $mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    $response = $this->postJson('/api/auth/magic/verify', [
        'email' => 'new@example.test',
        'code' => $code,
        'device_name' => 'iPhone',
        'timezone' => 'America/Edmonton',
    ])->assertOk()->assertJsonStructure(['token', 'user']);

    $user = User::where('email', 'new@example.test')->first();
    expect($user)->not->toBeNull()
        ->and($user->household_id)->not->toBeNull()
        ->and($user->familyMember)->not->toBeNull()
        ->and($user->timezone)->toBe('America/Edmonton')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($response->json('token'))->toBeString();

    expect(MagicLoginToken::where('email', 'new@example.test')->first()->used_at)->not->toBeNull();
});

it('refuses a wrong code', function () {
    $this->postJson('/api/auth/magic/request', ['email' => 'nope@example.test'])->assertOk();

    $this->postJson('/api/auth/magic/verify', ['email' => 'nope@example.test', 'code' => '000000'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');
});

it('refuses an expired code', function () {
    MagicLoginToken::create([
        'email' => 'old@example.test',
        'token_hash' => hash('sha256', 'tok'),
        'code_hash' => hash('sha256', '123456'),
        'expires_at' => now()->subMinute(),
    ]);

    $this->postJson('/api/auth/magic/verify', ['email' => 'old@example.test', 'code' => '123456'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');
});

it('refuses to reuse a spent code', function () {
    $this->postJson('/api/auth/magic/request', ['email' => 'once@example.test'])->assertOk();
    $code = null;
    Mail::assertSent(MagicLoginLink::class, function (MagicLoginLink $mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    $this->postJson('/api/auth/magic/verify', ['email' => 'once@example.test', 'code' => $code])->assertOk();
    $this->postJson('/api/auth/magic/verify', ['email' => 'once@example.test', 'code' => $code])
        ->assertStatus(422);
});

it('joins the household an invite code names', function () {
    $household = Household::create(['name' => 'Kotyks', 'invite_code' => 'JOINME12']);

    $this->postJson('/api/auth/magic/request', ['email' => 'guest@example.test'])->assertOk();
    $code = null;
    Mail::assertSent(MagicLoginLink::class, function (MagicLoginLink $mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    $this->postJson('/api/auth/magic/verify', [
        'email' => 'guest@example.test',
        'code' => $code,
        'invite_code' => 'joinme12',
    ])->assertOk();

    expect(User::where('email', 'guest@example.test')->first()->household_id)->toBe($household->id);
});

it('keeps a timezone the account already had', function () {
    $household = Household::create(['name' => 'H']);
    $user = User::create([
        'household_id' => $household->id,
        'name' => 'X',
        'email' => 'tz@example.test',
        'timezone' => 'Europe/Berlin',
    ]);

    $this->postJson('/api/auth/magic/request', ['email' => 'tz@example.test'])->assertOk();
    $code = null;
    Mail::assertSent(MagicLoginLink::class, function (MagicLoginLink $mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    $this->postJson('/api/auth/magic/verify', [
        'email' => 'tz@example.test',
        'code' => $code,
        'timezone' => 'America/Edmonton',
    ])->assertOk();

    expect($user->fresh()->timezone)->toBe('Europe/Berlin');
});
