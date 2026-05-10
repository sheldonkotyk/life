<?php

use App\Livewire\FamilyTree;
use App\Models\FamilyConnection;
use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeTreeHouseholdUser(): array
{
    $household = Household::create(['name' => 'Tree House']);
    $user = User::create([
        'household_id' => $household->id,
        'name' => 'Owner',
        'email' => 'tree-'.uniqid().'@test.test',
    ]);
    $user->joinHousehold($household);

    return [$household, $user];
}

it('groups members into generations from parent connections', function () {
    [$household, $user] = makeTreeHouseholdUser();
    $dad = FamilyMember::create(['household_id' => $household->id, 'name' => 'Dad']);
    $mom = FamilyMember::create(['household_id' => $household->id, 'name' => 'Mom']);
    $kid = FamilyMember::create(['household_id' => $household->id, 'name' => 'Kid']);
    $grandkid = FamilyMember::create(['household_id' => $household->id, 'name' => 'Grandkid']);

    FamilyConnection::create(['from_member_id' => $dad->id, 'to_member_id' => $kid->id, 'type' => 'father']);
    FamilyConnection::create(['from_member_id' => $mom->id, 'to_member_id' => $kid->id, 'type' => 'mother']);
    FamilyConnection::create(['from_member_id' => $kid->id, 'to_member_id' => $grandkid->id, 'type' => 'father']);

    Livewire::actingAs($user)
        ->test(FamilyTree::class)
        ->assertViewHas('rows', function ($rows) use ($dad, $mom, $kid, $grandkid) {
            $gen = [];
            foreach ($rows as $level => $row) {
                foreach ($row as $m) {
                    $gen[$m->id] = $level;
                }
            }

            return $gen[$dad->id] === 0
                && $gen[$mom->id] === 0
                && $gen[$kid->id] === 1
                && $gen[$grandkid->id] === 2;
        });
});

it('excludes guests from the tree', function () {
    [$household, $user] = makeTreeHouseholdUser();
    FamilyMember::create(['household_id' => $household->id, 'name' => 'Member']);
    FamilyMember::create(['household_id' => $household->id, 'name' => 'Visitor', 'is_guest' => true]);

    Livewire::actingAs($user)
        ->test(FamilyTree::class)
        ->assertViewHas('members', fn ($members) => $members->count() === 1 && $members->first()->name === 'Member');
});
