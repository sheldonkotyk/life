<?php

use App\Models\FamilyConnection;
use App\Models\FamilyMember;
use App\Models\GlobalRecipe;
use App\Models\GlobalRecipeIngredient;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeMemberRating;
use App\Models\TodoList;
use App\Notifications\UserNotification;
use Carbon\CarbonImmutable;

it('writes the API fixtures the Swift client is tested against', function () {
    $dir = getenv('FIXTURE_DIR');
    CarbonImmutable::setTestNow('2026-08-19 15:00:00 UTC');

    $user = loginApiUser();
    $user->update(['timezone' => 'America/Edmonton', 'birthday' => '1985-04-02']);

    $me = FamilyMember::create([
        'household_id' => $user->household_id,
        'user_id' => $user->id,
        'name' => 'Sheldon',
        'color' => '#6366f1',
        'target_calories' => 2400,
        'target_protein_g' => 160,
        'avatar_config' => ['hair' => ['style' => 'buzz'], 'eyes' => 'happy'],
    ]);
    $kid = FamilyMember::create([
        'household_id' => $user->household_id,
        'name' => 'Nora',
        'is_child' => true,
        'color' => '#f59e0b',
        'birthday' => '2016-02-11',
    ]);
    $guest = FamilyMember::create([
        'household_id' => $user->household_id,
        'name' => 'Alex',
        'is_guest' => true,
    ]);
    $me->preferences()->create(['food' => 'peanuts', 'type' => 'allergy']);
    $kid->setDefaultAttendance('mon', 'lunch', false);

    FamilyConnection::create(['from_member_id' => $me->id, 'to_member_id' => $kid->id, 'type' => 'father']);
    FamilyConnection::create(['from_member_id' => $guest->id, 'to_member_id' => $me->id, 'type' => 'girlfriend']);

    $recipe = Recipe::create([
        'household_id' => $user->household_id,
        'name' => 'Beef Tacos',
        'description' => 'Weeknight staple',
        'servings' => 4,
        'prep_minutes' => 25,
        'makes_leftovers' => true,
        'default_leftover_servings' => 2,
        'tags' => ['quick', 'family'],
        'instructions' => "Brown the beef.\nWarm the shells.",
        'source_url' => 'https://example.test/tacos',
    ]);
    RecipeIngredient::create([
        'recipe_id' => $recipe->id, 'name' => 'ground beef', 'quantity' => '500', 'unit' => 'g',
        'category' => 'Meat', 'sort_order' => 0, 'calories' => 1100, 'protein_g' => 100, 'carbs_g' => 0, 'fat_g' => 80,
    ]);
    RecipeIngredient::create([
        'recipe_id' => $recipe->id, 'name' => 'salt', 'quantity' => 'pinch', 'sort_order' => 1,
    ]);
    RecipeMemberRating::create(['recipe_id' => $recipe->id, 'family_member_id' => $me->id, 'rating' => 'love']);

    $plan = MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-08-19',
        'slot' => 'dinner',
        'recipe_id' => $recipe->id,
        'notes' => 'Extra salsa',
        'save_leftovers' => true,
        'leftover_servings' => 2,
        'start_time' => '18:00',
        'end_time' => '19:00',
    ]);
    $plan->attendees()->sync([
        $me->id => ['status' => 'eating'],
        $kid->id => ['status' => 'running_late'],
    ]);

    MealPlan::create([
        'household_id' => $user->household_id,
        'date' => '2026-08-17',
        'slot' => 'dinner',
        'recipe_id' => $recipe->id,
        'save_leftovers' => true,
        'leftover_servings' => 3,
    ]);

    $list = TodoList::create(['household_id' => $user->household_id, 'name' => 'Chores', 'color' => 'emerald']);
    $item = $list->items()->create([
        'title' => 'Take the bins out',
        'notes' => 'Blue bin this week',
        'due_date' => '2026-08-19',
        'recurrence_frequency' => 'weekly',
        'recurrence_interval' => 1,
        'position' => 1,
    ]);
    $item->assignees()->sync([$me->id]);
    $list->items()->create(['title' => 'Done thing', 'completed_at' => now(), 'position' => 2]);

    $global = GlobalRecipe::create([
        'source' => 'themealdb', 'external_id' => '52772', 'name' => 'Teriyaki Chicken',
        'category' => 'Chicken', 'area' => 'Japanese', 'instructions' => 'Cook.',
        'image_url' => 'https://example.test/t.jpg', 'tags' => ['Meat'],
        'source_url' => 'https://example.test/source', 'youtube_url' => 'https://example.test/yt',
    ]);
    GlobalRecipeIngredient::create(['global_recipe_id' => $global->id, 'name' => 'soy sauce', 'measure' => '3 tbsp', 'sort_order' => 0]);

    $user->notify(new UserNotification([
        'title' => 'Dinner ready', 'body' => 'Tacos are on the table', 'url' => '/today', 'channels' => ['database'],
    ]));

    $dumps = [
        'today' => '/api/today?date=2026-08-19',
        'planner' => '/api/planner?week_start=2026-08-17',
        'tracker' => '/api/tracker?date=2026-08-19',
        'calendar' => '/api/calendar?view=week&anchor=2026-08-19',
        'household' => '/api/household',
        'profile' => '/api/profile',
        'households' => '/api/profile/households',
        'family-members' => '/api/family-members',
        'connections' => '/api/connections',
        'family-tree' => '/api/family-tree',
        'recipes' => '/api/recipes',
        'global-recipes' => '/api/global-recipes',
        'global-recipe' => "/api/global-recipes/{$global->id}",
        'meal-plans' => '/api/meal-plans?from=2026-08-17&to=2026-08-23',
        'shopping-list' => '/api/shopping-list?from=2026-08-17&to=2026-08-23',
        'todo-lists' => '/api/todo-lists',
        'todo-items' => "/api/todo-lists/{$list->id}/items",
        'notifications' => '/api/notifications',
        'booking-pages' => '/api/booking-pages',
    ];

    foreach ($dumps as $name => $url) {
        $response = $this->getJson($url);
        expect($response->status())->toBe(200, "GET {$url}");
        // The raw body, not `->json()`: decoding it here would turn an empty
        // object into an empty array and hide exactly the shape the client
        // has to cope with.
        file_put_contents("{$dir}/{$name}.json", $response->getContent()."\n");
    }

    $toggle = $this->postJson("/api/todo-items/{$item->id}/toggle")->assertOk();
    file_put_contents("{$dir}/todo-toggle.json", $toggle->getContent()."\n");

    CarbonImmutable::setTestNow();
})->skip(fn () => getenv('FIXTURE_DIR') === false, 'Only runs when asked to write fixtures.');
