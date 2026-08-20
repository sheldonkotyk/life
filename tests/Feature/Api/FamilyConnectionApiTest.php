<?php

use App\Models\FamilyConnection;
use App\Models\FamilyMember;
use App\Models\Household;

it('lists the relationship vocabulary and the household\'s links', function () {
    $user = loginApiUser();
    $dad = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Dad']);
    $kid = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Kid']);
    FamilyConnection::create(['from_member_id' => $dad->id, 'to_member_id' => $kid->id, 'type' => 'father']);

    $response = $this->getJson('/api/connections')->assertOk();

    expect($response->json('types.father'))->toBe('Father of')
        ->and($response->json('connections'))->toHaveCount(1)
        ->and($response->json('connections.0.label'))->toBe('Father of')
        ->and($response->json('reciprocals.father'))->toBe(['son', 'daughter']);
});

it('creates a link and suggests the one coming back', function () {
    $user = loginApiUser();
    $dad = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Dad']);
    $kid = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Kid']);

    $response = $this->postJson('/api/connections', [
        'from_member_id' => $dad->id,
        'to_member_id' => $kid->id,
        'type' => 'father',
    ])->assertStatus(201);

    expect($response->json('suggested_reciprocal.from_member_id'))->toBe($kid->id)
        ->and($response->json('suggested_reciprocal.to_member_id'))->toBe($dad->id)
        ->and($response->json('suggested_reciprocal.options'))->toBe(['son', 'daughter']);
});

it('stops suggesting once the reverse link exists', function () {
    $user = loginApiUser();
    $dad = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Dad']);
    $kid = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Kid']);
    FamilyConnection::create(['from_member_id' => $kid->id, 'to_member_id' => $dad->id, 'type' => 'son']);

    $this->postJson('/api/connections', [
        'from_member_id' => $dad->id,
        'to_member_id' => $kid->id,
        'type' => 'father',
    ])->assertStatus(201)->assertJsonPath('suggested_reciprocal', null);
});

it('refuses a link to somebody else\'s household', function () {
    $user = loginApiUser();
    $mine = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Mine']);
    $theirs = FamilyMember::create([
        'household_id' => Household::create(['name' => 'X'])->id,
        'name' => 'Theirs',
    ]);

    $this->postJson('/api/connections', [
        'from_member_id' => $mine->id,
        'to_member_id' => $theirs->id,
        'type' => 'friend',
    ])->assertStatus(403);
});

it('refuses to connect somebody to themselves', function () {
    $user = loginApiUser();
    $me = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Me']);

    $this->postJson('/api/connections', [
        'from_member_id' => $me->id,
        'to_member_id' => $me->id,
        'type' => 'friend',
    ])->assertStatus(422);
});

it('deletes a link', function () {
    $user = loginApiUser();
    $a = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'A']);
    $b = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'B']);
    $connection = FamilyConnection::create(['from_member_id' => $a->id, 'to_member_id' => $b->id, 'type' => 'friend']);

    $this->deleteJson("/api/connections/{$connection->id}")->assertOk();
    expect(FamilyConnection::find($connection->id))->toBeNull();
});

it('sorts the tree into generations', function () {
    $user = loginApiUser();
    $grandma = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Grandma']);
    $mum = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Mum']);
    $kid = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Kid']);
    FamilyConnection::create(['from_member_id' => $grandma->id, 'to_member_id' => $mum->id, 'type' => 'mother']);
    FamilyConnection::create(['from_member_id' => $mum->id, 'to_member_id' => $kid->id, 'type' => 'mother']);

    $rows = $this->getJson('/api/family-tree')->assertOk()->json('rows');

    expect($rows[0])->toBe([$grandma->id])
        ->and($rows[1])->toBe([$mum->id])
        ->and($rows[2])->toBe([$kid->id]);
});

it('keeps an unattached guest out of the tree', function () {
    $user = loginApiUser();
    $member = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Member']);
    $guest = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Guest', 'is_guest' => true]);

    $ids = collect($this->getJson('/api/family-tree')->assertOk()->json('members'))->pluck('id')->all();

    expect($ids)->toContain($member->id)->not->toContain($guest->id);
});

it('lets a partnered guest into the tree beside their host', function () {
    $user = loginApiUser();
    $member = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Member']);
    $guest = FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Guest', 'is_guest' => true]);
    FamilyConnection::create([
        'from_member_id' => $guest->id,
        'to_member_id' => $member->id,
        'type' => 'girlfriend',
    ]);

    $response = $this->getJson('/api/family-tree')->assertOk();
    $ids = collect($response->json('members'))->pluck('id')->all();

    expect($ids)->toContain($guest->id)
        ->and($response->json("guests_of.{$member->id}"))->toBe([$guest->id])
        ->and($response->json('rows.0'))->toBe([$member->id]);
});
