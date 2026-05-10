<?php

use App\Livewire\FamilyConnections;
use App\Models\FamilyConnection;
use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeHouseholdUser(): array
{
    $household = Household::create(['name' => 'Test House']);
    $user = User::create([
        'household_id' => $household->id,
        'name' => 'Owner',
        'email' => 'owner-'.uniqid().'@test.test',
    ]);
    $user->joinHousehold($household);

    return [$household, $user];
}

it('adds a one-way connection without inferring a reciprocal', function () {
    [$household, $user] = makeHouseholdUser();
    $a = FamilyMember::create(['household_id' => $household->id, 'name' => 'Alex']);
    $b = FamilyMember::create(['household_id' => $household->id, 'name' => 'Bobbi', 'is_guest' => true]);

    Livewire::actingAs($user)
        ->test(FamilyConnections::class)
        ->set('fromId', $a->id)
        ->set('toId', $b->id)
        ->set('type', 'father')
        ->call('add')
        ->assertHasNoErrors();

    expect(FamilyConnection::count())->toBe(1);
    expect(FamilyConnection::where('from_member_id', $a->id)->where('to_member_id', $b->id)->first()->type)->toBe('father');
    expect(FamilyConnection::where('from_member_id', $b->id)->where('to_member_id', $a->id)->exists())->toBeFalse();
});

it('removes only the targeted connection', function () {
    [$household, $user] = makeHouseholdUser();
    $a = FamilyMember::create(['household_id' => $household->id, 'name' => 'Alex']);
    $b = FamilyMember::create(['household_id' => $household->id, 'name' => 'Bobbi']);

    $forward = FamilyConnection::create(['from_member_id' => $a->id, 'to_member_id' => $b->id, 'type' => 'husband']);
    FamilyConnection::create(['from_member_id' => $b->id, 'to_member_id' => $a->id, 'type' => 'wife']);

    Livewire::actingAs($user)
        ->test(FamilyConnections::class)
        ->call('remove', $forward->id);

    expect(FamilyConnection::count())->toBe(1);
    expect(FamilyConnection::where('from_member_id', $b->id)->where('to_member_id', $a->id)->first()->type)->toBe('wife');
});

it('blocks self-connections', function () {
    [$household, $user] = makeHouseholdUser();
    $a = FamilyMember::create(['household_id' => $household->id, 'name' => 'Alex']);

    Livewire::actingAs($user)
        ->test(FamilyConnections::class)
        ->set('fromId', $a->id)
        ->set('toId', $a->id)
        ->set('type', 'brother')
        ->call('add')
        ->assertHasErrors('fromId');

    expect(FamilyConnection::count())->toBe(0);
});

it('suggests a reciprocal after adding a connection', function () {
    [$household, $user] = makeHouseholdUser();
    $a = FamilyMember::create(['household_id' => $household->id, 'name' => 'Alex']);
    $b = FamilyMember::create(['household_id' => $household->id, 'name' => 'Bobbi']);

    Livewire::actingAs($user)
        ->test(FamilyConnections::class)
        ->set('fromId', $a->id)
        ->set('toId', $b->id)
        ->set('type', 'father')
        ->call('add')
        ->assertSet('reciprocalFromId', $b->id)
        ->assertSet('reciprocalToId', $a->id)
        ->assertSet('reciprocalOptions', ['son', 'daughter'])
        ->assertSet('reciprocalType', 'son');
});

it('creates the reciprocal when confirmed', function () {
    [$household, $user] = makeHouseholdUser();
    $a = FamilyMember::create(['household_id' => $household->id, 'name' => 'Alex']);
    $b = FamilyMember::create(['household_id' => $household->id, 'name' => 'Bobbi']);

    Livewire::actingAs($user)
        ->test(FamilyConnections::class)
        ->set('fromId', $a->id)
        ->set('toId', $b->id)
        ->set('type', 'father')
        ->call('add')
        ->set('reciprocalType', 'daughter')
        ->call('confirmReciprocal')
        ->assertSet('reciprocalFromId', null);

    expect(FamilyConnection::count())->toBe(2);
    expect(FamilyConnection::where('from_member_id', $b->id)->where('to_member_id', $a->id)->first()->type)->toBe('daughter');
});

it('dismisses the reciprocal suggestion without saving', function () {
    [$household, $user] = makeHouseholdUser();
    $a = FamilyMember::create(['household_id' => $household->id, 'name' => 'Alex']);
    $b = FamilyMember::create(['household_id' => $household->id, 'name' => 'Bobbi']);

    Livewire::actingAs($user)
        ->test(FamilyConnections::class)
        ->set('fromId', $a->id)
        ->set('toId', $b->id)
        ->set('type', 'husband')
        ->call('add')
        ->call('dismissReciprocal')
        ->assertSet('reciprocalFromId', null);

    expect(FamilyConnection::count())->toBe(1);
});

it('skips the suggestion when a reciprocal already exists', function () {
    [$household, $user] = makeHouseholdUser();
    $a = FamilyMember::create(['household_id' => $household->id, 'name' => 'Alex']);
    $b = FamilyMember::create(['household_id' => $household->id, 'name' => 'Bobbi']);

    FamilyConnection::create(['from_member_id' => $b->id, 'to_member_id' => $a->id, 'type' => 'wife']);

    Livewire::actingAs($user)
        ->test(FamilyConnections::class)
        ->set('fromId', $a->id)
        ->set('toId', $b->id)
        ->set('type', 'husband')
        ->call('add')
        ->assertSet('reciprocalFromId', null);
});

it('rejects connections to members in another household', function () {
    [$household, $user] = makeHouseholdUser();
    $a = FamilyMember::create(['household_id' => $household->id, 'name' => 'Alex']);

    $other = Household::create(['name' => 'Other']);
    $foreign = FamilyMember::create(['household_id' => $other->id, 'name' => 'Foreign']);

    Livewire::actingAs($user)
        ->test(FamilyConnections::class)
        ->set('fromId', $a->id)
        ->set('toId', $foreign->id)
        ->set('type', 'friend')
        ->call('add');

    expect(FamilyConnection::count())->toBe(0);
});
