<?php

namespace App\Http\Controllers;

use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login', [
            'devUsers' => app()->environment('local') ? User::orderBy('name')->get() : collect(),
            'appleEnabled' => filled(config('services.apple.client_id')),
        ]);
    }

    public function redirectToApple(): RedirectResponse
    {
        return Socialite::driver('apple')
            ->scopes(['name', 'email'])
            ->redirect();
    }

    public function appleCallback(): RedirectResponse
    {
        $appleUser = Socialite::driver('apple')->user();

        $user = User::firstOrNew(['apple_sub' => $appleUser->getId()]);
        $user->email = $appleUser->getEmail() ?: $user->email ?: ($appleUser->getId() . '@apple.private');
        $user->name = $appleUser->getName() ?: $user->name ?: 'Apple User';
        $user->avatar = $appleUser->getAvatar() ?: $user->avatar;

        if (! $user->household_id) {
            $household = Household::create(['name' => $user->name . "'s Household"]);
            $user->household_id = $household->id;
        }

        $user->save();

        if (! $user->familyMember) {
            FamilyMember::create([
                'household_id' => $user->household_id,
                'user_id' => $user->id,
                'name' => $user->name,
            ]);
        }

        Auth::login($user, true);

        return redirect()->intended('/');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }

    public function devLogin(Request $request, User $user): RedirectResponse
    {
        abort_unless(app()->environment('local'), 404);
        Auth::login($user, true);
        return redirect('/');
    }
}
