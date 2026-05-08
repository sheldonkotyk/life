<?php

use App\Http\Controllers\AppleNotificationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TimezoneController;
use App\Livewire\Availability;
use App\Livewire\Family;
use App\Livewire\HouseholdMealTimes;
use App\Livewire\HouseholdSettings;
use App\Livewire\Planner;
use App\Livewire\Profile;
use App\Livewire\RecipeBrowser;
use App\Livewire\Recipes;
use App\Livewire\ShoppingList;
use App\Livewire\Tonight;
use App\Livewire\Tracker;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect('/tonight') : view('landing');
})->name('home');

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
    Route::get('/tonight', Tonight::class)->name('tonight');
    Route::get('/plan', Planner::class)->name('plan');
    Route::get('/family', Family::class);
    Route::get('/recipes', Recipes::class);
    Route::get('/recipes/browse', RecipeBrowser::class)->name('recipes.browse');
    Route::get('/shopping', ShoppingList::class);
    Route::get('/tracker', Tracker::class);
    Route::get('/availability', Availability::class);
    Route::get('/profile', Profile::class)->name('profile');
    Route::get('/household', HouseholdSettings::class)->name('household');
    Route::get('/household/meals', HouseholdMealTimes::class)->name('household.meals');
    Route::post('/household/switch/{household}', [AuthController::class, 'switchHousehold'])->name('household.switch');
    Route::post('/me/timezone', [TimezoneController::class, 'detect']);
});
