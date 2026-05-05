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
Route::get('/join/{code}', [AuthController::class, 'joinViaLink'])->name('login.invite.link');
Route::post('/login/invite', [AuthController::class, 'applyInvite'])->name('login.invite');
Route::post('/login/invite/clear', [AuthController::class, 'clearInvite'])->name('login.invite.clear');
Route::get('/auth/apple/redirect', [AuthController::class, 'redirectToApple']);
Route::get('/auth/apple/callback', [AuthController::class, 'appleCallback']);
Route::post('/auth/apple/callback', [AuthController::class, 'appleCallback']);
Route::post('/auth/apple/notifications', AppleNotificationController::class)->name('apple.notifications');
Route::post('/auth/magic', [AuthController::class, 'requestMagicLink'])->name('auth.magic.request');
Route::post('/auth/magic/verify', [AuthController::class, 'verifyMagicCode'])->name('auth.magic.verify');
Route::get('/auth/magic/{token}', [AuthController::class, 'magicCallback'])->name('auth.magic.callback');
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
