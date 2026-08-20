<?php

use App\Models\FamilyMember;
use App\Models\FoodPreference;
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

it('includes family members, accounts and meal times', function () {
    $user = loginApiUser();
    $member = FamilyMember::create([
        'household_id' => $user->household_id,
        'user_id' => $user->id,
        'name' => 'Me',
    ]);
    FoodPreference::create([
        'family_member_id' => $member->id,
        'food' => 'peanuts',
        'type' => 'allergy',
    ]);

    $response = $this->getJson('/api/household')->assertOk();

    expect($response->json('members'))->toHaveCount(1)
        ->and($response->json('members.0.preferences.0.food'))->toBe('peanuts')
        ->and($response->json('users.0.id'))->toBe($user->id)
        ->and($response->json('meal_times.dinner.start'))->toBe('17:30')
        ->and($response->json('can_manage'))->toBeTrue();
});

it('updates the default meal times', function () {
    $user = loginApiUser();

    $this->patchJson('/api/household/meal-times', [
        'breakfast_start_time' => '06:30',
        'breakfast_end_time' => '08:00',
        'lunch_start_time' => '12:00',
        'lunch_end_time' => '13:00',
        'dinner_start_time' => '18:00',
        'dinner_end_time' => '19:00',
    ])->assertOk()->assertJsonPath('meal_times.dinner.start', '18:00');

    expect($user->household->fresh()->breakfast_start_time)->toStartWith('06:30');
});

it('refuses meal times that end before they start', function () {
    loginApiUser();

    $this->patchJson('/api/household/meal-times', [
        'breakfast_start_time' => '09:00',
        'breakfast_end_time' => '07:00',
        'lunch_start_time' => '12:00',
        'lunch_end_time' => '13:00',
        'dinner_start_time' => '18:00',
        'dinner_end_time' => '19:00',
    ])->assertStatus(422)->assertJsonValidationErrors('breakfast_end_time');
});

it('promotes and demotes administrators', function () {
    $user = loginApiUser();
    $household = $user->household;
    $household->users()->updateExistingPivot($user->id, ['role' => 'admin']);

    $other = User::create([
        'household_id' => $household->id,
        'name' => 'Other',
        'email' => 'other-admin@example.test',
    ]);
    $other->households()->syncWithoutDetaching([$household->id]);

    $this->postJson("/api/household/users/{$other->id}/admin")->assertOk();
    expect($other->fresh()->isAdminOf($household->fresh()))->toBeTrue();

    $this->deleteJson("/api/household/users/{$other->id}/admin")->assertOk();
    expect($other->fresh()->isAdminOf($household->fresh()))->toBeFalse();
});

it('will not demote the only administrator', function () {
    $user = loginApiUser();
    $user->household->users()->updateExistingPivot($user->id, ['role' => 'admin']);

    $this->deleteJson("/api/household/users/{$user->id}/admin")
        ->assertStatus(422)
        ->assertJsonValidationErrors('user');
});

it('dismisses and restores one-off meal names', function () {
    $user = loginApiUser();

    $this->postJson('/api/household/dismissed-meal-names', ['custom_name' => 'Pizza Night'])
        ->assertOk()
        ->assertJsonPath('dismissed_meal_names', ['Pizza Night']);

    expect($user->household->fresh()->dismissed_meal_names)->toBe(['Pizza Night']);

    $this->deleteJson('/api/household/dismissed-meal-names')
        ->assertOk()
        ->assertJsonPath('dismissed_meal_names', []);
});
