<?php

use App\Http\Controllers\AuthController;
use App\Livewire\Family;
use App\Livewire\Planner;
use App\Livewire\Recipes;
use App\Livewire\ShoppingList;
use App\Livewire\Tracker;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::get('/auth/apple/redirect', [AuthController::class, 'redirectToApple']);
Route::get('/auth/apple/callback', [AuthController::class, 'appleCallback']);
Route::post('/auth/apple/callback', [AuthController::class, 'appleCallback']);
Route::post('/dev-login/{user}', [AuthController::class, 'devLogin']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::middleware('auth')->group(function () {
    Route::get('/', Planner::class);
    Route::get('/family', Family::class);
    Route::get('/recipes', Recipes::class);
    Route::get('/shopping', ShoppingList::class);
    Route::get('/tracker', Tracker::class);
});
