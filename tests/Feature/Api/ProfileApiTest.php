<?php

use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\User;

it('returns the signed-in profile', function () {
    $user = loginApiUser();
    FamilyMember::create(['household_id' => $user->household_id, 'user_id' => $user->id, 'name' => 'Me']);

    $this->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('household_id', $user->household_id)
        ->assertJsonPath('timezone', 'UTC');
});

it('updates name, timezone and birthday', function () {
    $user = loginApiUser();

    $this->patchJson('/api/profile', [
        'name' => 'Sheldon',
        'timezone' => 'America/Edmonton',
        'birthday' => '1985-04-02',
    ])->assertOk()->assertJsonPath('name', 'Sheldon');

    expect($user->fresh()->timezone)->toBe('America/Edmonton')
        ->and($user->fresh()->birthday->toDateString())->toBe('1985-04-02');
});

it('refuses a timezone that is not a timezone', function () {
    loginApiUser();
    $this->patchJson('/api/profile', ['timezone' => 'Mars/Olympus_Mons'])->assertStatus(422);
});

it('turns the daily digest on by giving it a time and off by clearing it', function () {
    $user = loginApiUser();

    $this->patchJson('/api/profile', ['daily_today_email_at' => '07:30'])->assertOk();
    expect($user->fresh()->daily_today_email_enabled)->toBeTrue();

    $this->patchJson('/api/profile', ['daily_today_email_at' => null])
        ->assertOk()
        ->assertJsonPath('daily_today_email_at', null);
    expect($user->fresh()->daily_today_email_enabled)->toBeFalse();
});

it('stores every notification channel, not only the ones sent', function () {
    $user = loginApiUser();

    $this->patchJson('/api/profile', ['notification_preferences' => ['email' => true]])->assertOk();

    expect($user->fresh()->notification_preferences)
        ->toBe(['site' => false, 'email' => true, 'push' => false]);
});

it('lists the households I belong to and marks the current one', function () {
    $user = loginApiUser();
    $other = Household::create(['name' => 'Cabin']);
    $user->households()->syncWithoutDetaching([$other->id]);

    $response = $this->getJson('/api/profile/households')->assertOk();

    expect($response->json())->toHaveCount(2)
        ->and(collect($response->json())->firstWhere('id', $user->household_id)['is_current'])->toBeTrue()
        ->and(collect($response->json())->firstWhere('id', $other->id)['is_current'])->toBeFalse();
});

it('creates a household and switches into it', function () {
    $user = loginApiUser();

    $response = $this->postJson('/api/profile/households', ['name' => 'Lake House'])->assertStatus(201);

    expect($user->fresh()->household_id)->toBe($response->json('id'))
        ->and($response->json('is_admin'))->toBeTrue();
});

it('joins a household by invite code', function () {
    $user = loginApiUser();
    $other = Household::create(['name' => 'Cabin', 'invite_code' => 'CABIN123']);

    $this->postJson('/api/profile/households/join', ['invite_code' => 'cabin123'])->assertOk();

    expect($user->fresh()->household_id)->toBe($other->id);
});

it('rejects an unknown invite code', function () {
    loginApiUser();

    $this->postJson('/api/profile/households/join', ['invite_code' => 'NOPE0000'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('invite_code');
});

it('refuses to join a household twice', function () {
    $user = loginApiUser();

    $this->postJson('/api/profile/households/join', ['invite_code' => $user->household->invite_code])
        ->assertStatus(422);
});

it('switches between my households', function () {
    $user = loginApiUser();
    $other = Household::create(['name' => 'Cabin']);
    $user->households()->syncWithoutDetaching([$other->id]);

    $this->postJson("/api/profile/households/{$other->id}/switch")->assertOk();

    expect($user->fresh()->household_id)->toBe($other->id);
});

it('will not switch into a household I am not in', function () {
    loginApiUser();
    $other = Household::create(['name' => 'Stranger']);

    $this->postJson("/api/profile/households/{$other->id}/switch")->assertStatus(403);
});

it('leaves a household and lands in another one', function () {
    $user = loginApiUser();
    $original = $user->household_id;
    $other = Household::create(['name' => 'Cabin']);
    $user->households()->syncWithoutDetaching([$other->id]);

    $this->deleteJson("/api/profile/households/{$original}")->assertOk();

    expect($user->fresh()->household_id)->toBe($other->id)
        ->and(Household::find($original))->toBeNull();
});

it('will not let the last admin leave people behind', function () {
    $user = loginApiUser();
    $household = $user->household;
    $household->users()->updateExistingPivot($user->id, ['role' => 'admin']);

    $other = User::create([
        'household_id' => $household->id,
        'name' => 'Other',
        'email' => 'other@example.test',
    ]);
    $other->households()->syncWithoutDetaching([$household->id]);

    $this->deleteJson("/api/profile/households/{$household->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors('household');
});
