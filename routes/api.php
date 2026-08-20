<?php

use App\Http\Controllers\Api\ApiAuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\FamilyConnectionController;
use App\Http\Controllers\Api\FamilyMemberController;
use App\Http\Controllers\Api\GlobalRecipeController;
use App\Http\Controllers\Api\HouseholdController;
use App\Http\Controllers\Api\MealPlanController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PlannerController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RecipeController;
use App\Http\Controllers\Api\TodayController;
use App\Http\Controllers\Api\TodoItemController;
use App\Http\Controllers\Api\TodoListController;
use App\Http\Controllers\Api\TrackerController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/apple', [ApiAuthController::class, 'apple']);
Route::post('/auth/magic/request', [ApiAuthController::class, 'requestMagicCode']);
Route::post('/auth/magic/verify', [ApiAuthController::class, 'verifyMagicCode']);
Route::post('/auth/dev-token', [ApiAuthController::class, 'devToken']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [ApiAuthController::class, 'me']);
    Route::post('/logout', [ApiAuthController::class, 'logout']);

    // Profile and household membership
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::get('/profile/households', [ProfileController::class, 'households']);
    Route::post('/profile/households', [ProfileController::class, 'createHousehold']);
    Route::post('/profile/households/join', [ProfileController::class, 'joinHousehold']);
    Route::post('/profile/households/{household}/switch', [ProfileController::class, 'switchHousehold']);
    Route::delete('/profile/households/{household}', [ProfileController::class, 'leaveHousehold']);

    // Household
    Route::get('/household', [HouseholdController::class, 'show']);
    Route::patch('/household', [HouseholdController::class, 'updateName']);
    Route::patch('/household/meal-times', [HouseholdController::class, 'updateMealTimes']);
    Route::post('/household/rotate-invite', [HouseholdController::class, 'rotateInvite']);
    Route::post('/household/join', [HouseholdController::class, 'join']);
    Route::post('/household/users/{user}/admin', [HouseholdController::class, 'makeAdmin']);
    Route::delete('/household/users/{user}/admin', [HouseholdController::class, 'removeAdmin']);
    Route::post('/household/dismissed-meal-names', [HouseholdController::class, 'dismissMealName']);
    Route::delete('/household/dismissed-meal-names', [HouseholdController::class, 'restoreDismissedMealNames']);

    // Family members
    Route::get('/family-members', [FamilyMemberController::class, 'index']);
    Route::post('/family-members', [FamilyMemberController::class, 'store']);
    Route::get('/family-members/{member}', [FamilyMemberController::class, 'show']);
    Route::patch('/family-members/{member}', [FamilyMemberController::class, 'update']);
    Route::delete('/family-members/{member}', [FamilyMemberController::class, 'destroy']);
    Route::post('/family-members/{member}/attendance-defaults', [FamilyMemberController::class, 'setDefaultAttendance']);
    Route::post('/family-members/{member}/preferences', [FamilyMemberController::class, 'addPreference']);
    Route::delete('/preferences/{preference}', [FamilyMemberController::class, 'removePreference']);

    // Relationships
    Route::get('/connections', [FamilyConnectionController::class, 'index']);
    Route::post('/connections', [FamilyConnectionController::class, 'store']);
    Route::delete('/connections/{connection}', [FamilyConnectionController::class, 'destroy']);
    Route::get('/family-tree', [FamilyConnectionController::class, 'tree']);

    // Screens
    Route::get('/today', [TodayController::class, 'show']);
    Route::get('/planner', [PlannerController::class, 'index']);
    Route::post('/planner/attendance', [PlannerController::class, 'setAttendance']);
    Route::get('/tracker', [TrackerController::class, 'show']);
    Route::get('/calendar', [CalendarController::class, 'index']);

    // Recipes
    Route::get('/recipes', [RecipeController::class, 'index']);
    Route::post('/recipes', [RecipeController::class, 'store']);
    Route::get('/recipes/{recipe}', [RecipeController::class, 'show']);
    Route::patch('/recipes/{recipe}', [RecipeController::class, 'update']);
    Route::delete('/recipes/{recipe}', [RecipeController::class, 'destroy']);

    Route::get('/global-recipes', [GlobalRecipeController::class, 'index']);
    Route::post('/global-recipes/discover', [GlobalRecipeController::class, 'discover']);
    Route::post('/global-recipes/import', [GlobalRecipeController::class, 'import']);
    Route::get('/global-recipes/{globalRecipe}', [GlobalRecipeController::class, 'show']);
    Route::post('/global-recipes/{globalRecipe}/adopt', [GlobalRecipeController::class, 'adopt']);

    // Meal plans
    Route::get('/meal-plans', [MealPlanController::class, 'index']);
    Route::post('/meal-plans', [MealPlanController::class, 'store']);
    Route::patch('/meal-plans/{plan}', [MealPlanController::class, 'update']);
    Route::delete('/meal-plans/{plan}', [MealPlanController::class, 'destroy']);
    Route::post('/meal-plans/{plan}/attendance', [MealPlanController::class, 'setAttendance']);
    Route::post('/meal-plans/{plan}/move', [MealPlanController::class, 'move']);

    Route::get('/shopping-list', [MealPlanController::class, 'shoppingList']);

    // Lists
    Route::get('/todo-lists', [TodoListController::class, 'index']);
    Route::post('/todo-lists', [TodoListController::class, 'store']);
    Route::post('/todo-lists/reorder', [TodoListController::class, 'reorder']);
    Route::patch('/todo-lists/{list}', [TodoListController::class, 'update']);
    Route::delete('/todo-lists/{list}', [TodoListController::class, 'destroy']);
    Route::get('/todo-lists/{list}/items', [TodoItemController::class, 'index']);
    Route::post('/todo-lists/{list}/items', [TodoItemController::class, 'store']);
    Route::post('/todo-lists/{list}/items/reorder', [TodoItemController::class, 'reorder']);
    Route::patch('/todo-items/{item}', [TodoItemController::class, 'update']);
    Route::delete('/todo-items/{item}', [TodoItemController::class, 'destroy']);
    Route::post('/todo-items/{item}/toggle', [TodoItemController::class, 'toggle']);
    Route::post('/todo-items/{item}/move', [TodoItemController::class, 'move']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

    // Bookings
    Route::get('/booking-pages', [BookingController::class, 'index']);
    Route::patch('/booking-pages/{bookingPage}', [BookingController::class, 'update']);
    Route::get('/booking-pages/{bookingPage}/bookings', [BookingController::class, 'bookings']);
    Route::post('/bookings/{booking}/accept', [BookingController::class, 'accept']);
    Route::post('/bookings/{booking}/decline', [BookingController::class, 'decline']);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
});
