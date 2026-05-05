<?php

use App\Http\Controllers\Api\ApiAuthController;
use App\Http\Controllers\Api\FamilyMemberController;
use App\Http\Controllers\Api\HouseholdController;
use App\Http\Controllers\Api\MealPlanController;
use App\Http\Controllers\Api\RecipeController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/apple', [ApiAuthController::class, 'apple']);
Route::post('/auth/dev-token', [ApiAuthController::class, 'devToken']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [ApiAuthController::class, 'me']);
    Route::post('/logout', [ApiAuthController::class, 'logout']);

    Route::get('/household', [HouseholdController::class, 'show']);
    Route::patch('/household', [HouseholdController::class, 'updateName']);
    Route::post('/household/rotate-invite', [HouseholdController::class, 'rotateInvite']);
    Route::post('/household/join', [HouseholdController::class, 'join']);

    Route::get('/family-members', [FamilyMemberController::class, 'index']);
    Route::post('/family-members', [FamilyMemberController::class, 'store']);
    Route::patch('/family-members/{member}', [FamilyMemberController::class, 'update']);
    Route::delete('/family-members/{member}', [FamilyMemberController::class, 'destroy']);
    Route::post('/family-members/{member}/preferences', [FamilyMemberController::class, 'addPreference']);
    Route::delete('/preferences/{preference}', [FamilyMemberController::class, 'removePreference']);

    Route::get('/recipes', [RecipeController::class, 'index']);
    Route::post('/recipes', [RecipeController::class, 'store']);
    Route::get('/recipes/{recipe}', [RecipeController::class, 'show']);
    Route::patch('/recipes/{recipe}', [RecipeController::class, 'update']);
    Route::delete('/recipes/{recipe}', [RecipeController::class, 'destroy']);

    Route::get('/meal-plans', [MealPlanController::class, 'index']);
    Route::post('/meal-plans', [MealPlanController::class, 'store']);
    Route::patch('/meal-plans/{plan}', [MealPlanController::class, 'update']);
    Route::delete('/meal-plans/{plan}', [MealPlanController::class, 'destroy']);

    Route::get('/shopping-list', [MealPlanController::class, 'shoppingList']);
});
