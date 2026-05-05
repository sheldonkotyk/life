<?php

namespace Database\Seeders;

use App\Models\FamilyMember;
use App\Models\FoodPreference;
use App\Models\Household;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeMemberRating;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $hh = Household::create(['name' => 'The Kotyk Family']);

        $sheldon = User::create([
            'household_id' => $hh->id,
            'name' => 'Sheldon',
            'email' => 'sheldon@example.test',
        ]);
        $partner = User::create([
            'household_id' => $hh->id,
            'name' => 'Partner',
            'email' => 'partner@example.test',
        ]);

        $members = collect([
            ['name' => 'Sheldon', 'color' => '#4f46e5', 'is_child' => false, 'user_id' => $sheldon->id],
            ['name' => 'Partner', 'color' => '#db2777', 'is_child' => false, 'user_id' => $partner->id],
            ['name' => 'Kid 1', 'color' => '#0ea5e9', 'is_child' => true, 'user_id' => null],
            ['name' => 'Kid 2', 'color' => '#16a34a', 'is_child' => true, 'user_id' => null],
            ['name' => 'Kid 3', 'color' => '#f59e0b', 'is_child' => true, 'user_id' => null],
        ])->map(fn($m) => FamilyMember::create([...$m, 'household_id' => $hh->id]));

        $prefs = [
            'Sheldon' => [['like', 'spicy food'], ['like', 'BBQ'], ['dislike', 'mushrooms']],
            'Partner' => [['like', 'salads'], ['allergy', 'shellfish']],
            'Kid 1' => [['like', 'pasta'], ['dislike', 'broccoli']],
            'Kid 2' => [['like', 'chicken nuggets'], ['dislike', 'fish'], ['allergy', 'peanuts']],
            'Kid 3' => [['like', 'pizza'], ['like', 'cheese'], ['dislike', 'onions']],
        ];
        foreach ($prefs as $name => $items) {
            $member = $members->firstWhere('name', $name);
            foreach ($items as [$type, $food]) {
                FoodPreference::create(['family_member_id' => $member->id, 'type' => $type, 'food' => $food]);
            }
        }

        $recipes = [
            [
                'name' => 'Spaghetti Bolognese',
                'description' => 'Classic family weeknight dinner.',
                'servings' => 6, 'prep_minutes' => 30,
                'makes_leftovers' => true, 'default_leftover_servings' => 3,
                'ingredients' => [
                    ['1', 'lb', 'ground beef', 'Meat'],
                    ['1', '', 'onion, diced', 'Produce'],
                    ['3', 'cloves', 'garlic, minced', 'Produce'],
                    ['1', 'jar', 'marinara sauce', 'Pantry'],
                    ['1', 'lb', 'spaghetti', 'Pantry'],
                    ['', '', 'parmesan to serve', 'Dairy'],
                ],
                'ratings' => ['Sheldon' => 'love', 'Partner' => 'ok', 'Kid 1' => 'love', 'Kid 2' => 'love', 'Kid 3' => 'ok'],
            ],
            [
                'name' => 'Sheet Pan Chicken Fajitas',
                'description' => 'Easy one-pan meal.',
                'servings' => 5, 'prep_minutes' => 35,
                'makes_leftovers' => true, 'default_leftover_servings' => 2,
                'ingredients' => [
                    ['1.5', 'lb', 'chicken breast, sliced', 'Meat'],
                    ['3', '', 'bell peppers, sliced', 'Produce'],
                    ['1', '', 'onion, sliced', 'Produce'],
                    ['1', 'pkg', 'flour tortillas', 'Bakery'],
                    ['1', 'tbsp', 'fajita seasoning', 'Pantry'],
                    ['1', '', 'lime', 'Produce'],
                ],
                'ratings' => ['Sheldon' => 'love', 'Partner' => 'love', 'Kid 1' => 'ok', 'Kid 3' => 'ok'],
            ],
            [
                'name' => 'Homemade Pizza',
                'description' => 'Friday night tradition.',
                'servings' => 5, 'prep_minutes' => 45,
                'makes_leftovers' => false,
                'ingredients' => [
                    ['2', '', 'pizza dough balls', 'Bakery'],
                    ['1', 'cup', 'pizza sauce', 'Pantry'],
                    ['2', 'cups', 'mozzarella, shredded', 'Dairy'],
                    ['', '', 'pepperoni', 'Meat'],
                ],
                'ratings' => ['Sheldon' => 'love', 'Partner' => 'love', 'Kid 1' => 'love', 'Kid 2' => 'love', 'Kid 3' => 'love'],
            ],
            [
                'name' => 'Tacos',
                'servings' => 5, 'prep_minutes' => 25,
                'makes_leftovers' => true, 'default_leftover_servings' => 2,
                'ingredients' => [
                    ['1', 'lb', 'ground turkey', 'Meat'],
                    ['1', 'pkg', 'taco seasoning', 'Pantry'],
                    ['10', '', 'taco shells', 'Pantry'],
                    ['1', 'cup', 'shredded cheddar', 'Dairy'],
                    ['1', 'head', 'lettuce', 'Produce'],
                    ['2', '', 'tomatoes', 'Produce'],
                ],
                'ratings' => ['Sheldon' => 'love', 'Kid 1' => 'love', 'Kid 3' => 'love'],
            ],
            [
                'name' => 'Veggie Stir Fry',
                'servings' => 4, 'prep_minutes' => 20,
                'makes_leftovers' => false,
                'ingredients' => [
                    ['2', 'cups', 'broccoli florets', 'Produce'],
                    ['1', '', 'carrot, sliced', 'Produce'],
                    ['1', '', 'bell pepper', 'Produce'],
                    ['3', 'tbsp', 'soy sauce', 'Pantry'],
                    ['1', 'cup', 'jasmine rice', 'Pantry'],
                ],
                'ratings' => ['Partner' => 'love', 'Sheldon' => 'ok', 'Kid 1' => 'dislike', 'Kid 2' => 'ok'],
            ],
            [
                'name' => 'Pancakes',
                'description' => 'Weekend breakfast.',
                'servings' => 5, 'prep_minutes' => 20,
                'makes_leftovers' => false,
                'ingredients' => [
                    ['2', 'cups', 'flour', 'Pantry'],
                    ['2', 'tbsp', 'sugar', 'Pantry'],
                    ['2', '', 'eggs', 'Dairy'],
                    ['1.5', 'cups', 'milk', 'Dairy'],
                    ['', '', 'maple syrup', 'Pantry'],
                ],
                'ratings' => ['Kid 1' => 'love', 'Kid 2' => 'love', 'Kid 3' => 'love', 'Sheldon' => 'love', 'Partner' => 'ok'],
            ],
        ];

        foreach ($recipes as $r) {
            $recipe = Recipe::create([
                'household_id' => $hh->id,
                'name' => $r['name'],
                'description' => $r['description'] ?? null,
                'servings' => $r['servings'],
                'prep_minutes' => $r['prep_minutes'] ?? null,
                'makes_leftovers' => $r['makes_leftovers'] ?? false,
                'default_leftover_servings' => $r['default_leftover_servings'] ?? 0,
            ]);
            foreach ($r['ingredients'] as $i => [$qty, $unit, $name, $cat]) {
                RecipeIngredient::create([
                    'recipe_id' => $recipe->id,
                    'name' => $name, 'quantity' => $qty ?: null, 'unit' => $unit ?: null,
                    'category' => $cat, 'sort_order' => $i,
                ]);
            }
            foreach ($r['ratings'] ?? [] as $name => $rating) {
                $member = $members->firstWhere('name', $name);
                if ($member) {
                    RecipeMemberRating::create([
                        'recipe_id' => $recipe->id,
                        'family_member_id' => $member->id,
                        'rating' => $rating,
                    ]);
                }
            }
        }

        // A few sample meal plans for this week
        $monday = CarbonImmutable::now()->startOfWeek();
        $bolognese = Recipe::where('name', 'Spaghetti Bolognese')->first();
        $fajitas = Recipe::where('name', 'Sheet Pan Chicken Fajitas')->first();
        $pizza = Recipe::where('name', 'Homemade Pizza')->first();
        $pancakes = Recipe::where('name', 'Pancakes')->first();
        $allMembers = $members->pluck('id')->all();

        $plan1 = MealPlan::create([
            'household_id' => $hh->id, 'date' => $monday->toDateString(), 'slot' => 'dinner',
            'recipe_id' => $bolognese->id, 'save_leftovers' => true, 'leftover_servings' => 3,
        ]);
        $plan1->attendees()->sync($allMembers);

        // Tuesday lunch = leftovers from Monday
        $plan2 = MealPlan::create([
            'household_id' => $hh->id, 'date' => $monday->addDay()->toDateString(), 'slot' => 'lunch',
            'leftover_of_id' => $plan1->id,
        ]);
        $plan2->attendees()->sync([$members->firstWhere('name', 'Sheldon')->id]);

        $plan3 = MealPlan::create([
            'household_id' => $hh->id, 'date' => $monday->addDays(2)->toDateString(), 'slot' => 'dinner',
            'recipe_id' => $fajitas->id,
        ]);
        $plan3->attendees()->sync($allMembers);

        $plan4 = MealPlan::create([
            'household_id' => $hh->id, 'date' => $monday->addDays(4)->toDateString(), 'slot' => 'dinner',
            'recipe_id' => $pizza->id,
        ]);
        $plan4->attendees()->sync($allMembers);

        $plan5 = MealPlan::create([
            'household_id' => $hh->id, 'date' => $monday->addDays(5)->toDateString(), 'slot' => 'breakfast',
            'recipe_id' => $pancakes->id,
        ]);
        $plan5->attendees()->sync($allMembers);
    }
}
