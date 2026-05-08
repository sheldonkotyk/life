<?php

use App\Models\FamilyMember;

it('renders the edit-meal modal body when an empty slot is clicked', function () {
    $user = loginUser();
    FamilyMember::create(['household_id' => $user->household_id, 'name' => 'Ava']);

    $page = visit('/meal-plan');

    $page->assertSee('Meal Plan')
        ->assertNoJavaScriptErrors()
        // Regression: the modal lives outside the plan island, so an action
        // fired from inside the island would only re-render the island and
        // leave the modal body empty. Clicking the first empty slot must
        // populate the modal body.
        ->script('document.querySelector(\'button[wire\\\\:click^="openSlot"]\').click()');

    $page->assertSee('Recipe')
        ->assertSee("Who's eating?")
        ->assertSee('Ava');
});
