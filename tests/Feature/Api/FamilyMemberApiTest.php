<?php

use App\Models\FamilyMember;
use App\Models\FoodPreference;
use App\Models\Household;

it('lists family members scoped to household', function () {
    $user = loginApiUser();
    FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Mine']);
    $other = Household::create(['name' => 'Other']);
    FamilyMember::create(['household_id' => $other->id, 'name' => 'Theirs']);

    $response = $this->getJson('/api/family-members')->assertOk();
    $names = collect($response->json())->pluck('name')->all();
    expect($names)->toBe(['Mine']);
});

it('creates a family member', function () {
    $user = loginApiUser();

    $this->postJson('/api/family-members', [
        'name' => 'Kiddo',
        'is_child' => true,
        'color' => '#ff0000',
    ])->assertStatus(201);

    expect(FamilyMember::where('household_id', $user->household_id)->where('name', 'Kiddo')->exists())->toBeTrue();
});

it('updates a family member', function () {
    $user = loginApiUser();
    $member = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'A']);

    $this->patchJson("/api/family-members/{$member->id}", ['name' => 'B'])->assertOk();
    expect($member->fresh()->name)->toBe('B');
});

it('blocks updating a member from another household', function () {
    loginApiUser();
    $other = Household::create(['name' => 'Other']);
    $member = FamilyMember::create(['household_id' => $other->id, 'name' => 'Theirs']);

    $this->patchJson("/api/family-members/{$member->id}", ['name' => 'Hijack'])->assertStatus(403);
});

it('deletes a family member', function () {
    $user = loginApiUser();
    $member = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Bye']);

    $this->deleteJson("/api/family-members/{$member->id}")->assertOk();
    expect(FamilyMember::find($member->id))->toBeNull();
});

it('adds a preference for a member', function () {
    $user = loginApiUser();
    $member = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Eater']);

    $this->postJson("/api/family-members/{$member->id}/preferences", [
        'food' => 'peanuts',
        'type' => 'allergy',
    ])->assertStatus(201);

    expect($member->preferences()->count())->toBe(1);
});

it('rejects invalid preference type', function () {
    $user = loginApiUser();
    $member = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Eater']);

    $this->postJson("/api/family-members/{$member->id}/preferences", [
        'food' => 'x',
        'type' => 'bogus',
    ])->assertStatus(422);
});

it('removes a preference', function () {
    $user = loginApiUser();
    $member = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Eater']);
    $pref = FoodPreference::create([
        'family_member_id' => $member->id,
        'food' => 'olives',
        'type' => 'dislike',
    ]);

    $this->deleteJson("/api/preferences/{$pref->id}")->assertOk();
    expect(FoodPreference::find($pref->id))->toBeNull();
});

it('sets default attendance for a whole day', function () {
    $user = loginApiUser();
    $member = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'A']);

    $this->postJson("/api/family-members/{$member->id}/attendance-defaults", [
        'attending' => false,
        'days' => ['mon'],
    ])->assertOk();

    $member->refresh();
    expect($member->attendsByDefault('mon', 'dinner'))->toBeFalse()
        ->and($member->attendsByDefault('tue', 'dinner'))->toBeTrue();
});

it('sets default attendance for one slot across the week', function () {
    $user = loginApiUser();
    $member = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'A']);

    $this->postJson("/api/family-members/{$member->id}/attendance-defaults", [
        'attending' => false,
        'slots' => ['lunch'],
    ])->assertOk();

    $member->refresh();
    expect($member->attendsByDefault('mon', 'lunch'))->toBeFalse()
        ->and($member->attendsByDefault('mon', 'dinner'))->toBeTrue();
});

it('rejects a day that is not a day', function () {
    $user = loginApiUser();
    $member = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'A']);

    $this->postJson("/api/family-members/{$member->id}/attendance-defaults", [
        'attending' => true,
        'days' => ['funday'],
    ])->assertStatus(422);
});

it('normalises a built avatar before storing it', function () {
    $user = loginApiUser();
    $member = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'A']);

    $this->patchJson("/api/family-members/{$member->id}", [
        'avatar_config' => ['hair' => ['style' => 'bun']],
    ])->assertOk();

    $config = $member->fresh()->avatar_config;
    expect($config['hair']['style'])->toBe('bun')
        ->and($config)->toHaveKey('skin')
        ->and($config)->toHaveKey('eyes');
});

it('keeps a member\'s account name in step with their name', function () {
    $user = loginApiUser();
    $member = FamilyMember::create([
        'household_id' => $user->household_id,
        'user_id' => $user->id,
        'name' => 'Old',
    ]);

    $this->patchJson("/api/family-members/{$member->id}", ['name' => 'New'])->assertOk();

    expect($user->fresh()->name)->toBe('New');
});

it('refuses to delete a member who is an account', function () {
    $user = loginApiUser();
    $member = FamilyMember::create([
        'household_id' => $user->household_id,
        'user_id' => $user->id,
        'name' => 'Me',
    ]);

    $this->deleteJson("/api/family-members/{$member->id}")->assertStatus(403);
});
