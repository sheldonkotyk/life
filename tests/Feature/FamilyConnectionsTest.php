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

it('adds a connection and creates the reciprocal', function () {
    [$household, $user] = makeHouseholdUser();
    $a = FamilyMember::create(['household_id' => $household->id, 'name' => 'Alex']);
    $b = FamilyMember::create(['household_id' => $household->id, 'name' => 'Bobbi', 'is_guest' => true]);

    Livewire::actingAs($user)
        ->test(FamilyConnections::class)
        ->set('fromId', $a->id)
        ->set('toId', $b->id)
        ->set('type', 'parent')
        ->call('add')
        ->assertHasNoErrors();

    expect(FamilyConnection::count())->toBe(2);
    expect(FamilyConnection::where('from_member_id', $a->id)->where('to_member_id', $b->id)->first()->type)->toBe('parent');
    expect(FamilyConnection::where('from_member_id', $b->id)->where('to_member_id', $a->id)->first()->type)->toBe('child');
});

it('removes both sides of a reciprocal connection', function () {
    [$household, $user] = makeHouseholdUser();
    $a = FamilyMember::create(['household_id' => $household->id, 'name' => 'Alex']);
    $b = FamilyMember::create(['household_id' => $household->id, 'name' => 'Bobbi']);

    $forward = FamilyConnection::create(['from_member_id' => $a->id, 'to_member_id' => $b->id, 'type' => 'spouse']);
    FamilyConnection::create(['from_member_id' => $b->id, 'to_member_id' => $a->id, 'type' => 'spouse']);

    Livewire::actingAs($user)
        ->test(FamilyConnections::class)
        ->call('remove', $forward->id);

    expect(FamilyConnection::count())->toBe(0);
});

it('blocks self-connections', function () {
    [$household, $user] = makeHouseholdUser();
    $a = FamilyMember::create(['household_id' => $household->id, 'name' => 'Alex']);

    Livewire::actingAs($user)
        ->test(FamilyConnections::class)
        ->set('fromId', $a->id)
        ->set('toId', $a->id)
        ->set('type', 'sibling')
        ->call('add')
        ->assertHasErrors('fromId');

    expect(FamilyConnection::count())->toBe(0);
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
