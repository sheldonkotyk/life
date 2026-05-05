<?php

use App\Http\Controllers\AppleNotificationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TimezoneController;
use App\Livewire\Availability;
use App\Livewire\Family;
use App\Livewire\HouseholdSettings;
use App\Livewire\Planner;
use App\Livewire\Profile;
use App\Livewire\Recipes;
use App\Livewire\ShoppingList;
use App\Livewire\Tracker;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login/invite', [AuthController::class, 'applyInvite'])->name('login.invite');
Route::post('/login/invite/clear', [AuthController::class, 'clearInvite'])->name('login.invite.clear');
Route::get('/auth/apple/redirect', [AuthController::class, 'redirectToApple']);
Route::get('/auth/apple/callback', [AuthController::class, 'appleCallback']);
Route::post('/auth/apple/callback', [AuthController::class, 'appleCallback']);
Route::post('/auth/apple/notifications', AppleNotificationController::class)->name('apple.notifications');
Route::post('/dev-login/{user}', [AuthController::class, 'devLogin']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::middleware('auth')->group(function () {
    Route::get('/', Planner::class);
    Route::get('/family', Family::class);
    Route::get('/recipes', Recipes::class);
    Route::get('/shopping', ShoppingList::class);
    Route::get('/tracker', Tracker::class);
    Route::get('/availability', Availability::class);
    Route::get('/profile', Profile::class)->name('profile');
    Route::get('/household', HouseholdSettings::class)->name('household');
    Route::post('/me/timezone', [TimezoneController::class, 'detect']);
});
