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
        $pendingCode = session('pending_invite_code');
        $pendingHousehold = $pendingCode
            ? Household::where('invite_code', $pendingCode)->first()
            : null;

        return view('auth.login', [
            'devUsers' => app()->environment('local') ? User::orderBy('name')->get() : collect(),
            'appleEnabled' => filled(config('services.apple.client_id')),
            'pendingHousehold' => $pendingHousehold,
        ]);
    }

    public function applyInvite(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invite_code' => ['required', 'string', 'max:12'],
        ]);

        $code = strtoupper(trim($data['invite_code']));
        $household = Household::where('invite_code', $code)->first();

        if (! $household) {
            return back()->withErrors(['invite_code' => 'No household found for that code.']);
        }

        $request->session()->put('pending_invite_code', $code);

        return back()->with('status', 'You\'ll join ' . $household->name . ' after signing in.');
    }

    public function clearInvite(Request $request): RedirectResponse
    {
        $request->session()->forget('pending_invite_code');
        return back();
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
        $user->name = $appleUser->getName() ?: $user->name ?: 'You';
        $user->avatar = $appleUser->getAvatar() ?: $user->avatar;

        $invitedHousehold = $this->pullInvitedHousehold();

        $newHousehold = null;
        if (! $user->household_id && ! $invitedHousehold) {
            $householdName = $appleUser->getName() ? $user->name . "'s Household" : 'Your Household';
            $newHousehold = Household::create(['name' => $householdName]);
        }

        $user->save();

        if ($newHousehold) {
            $user->joinHousehold($newHousehold);
        }

        if ($invitedHousehold) {
            $user->joinHousehold($invitedHousehold);
        }

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

        if ($invited = $this->pullInvitedHousehold()) {
            $user->joinHousehold($invited);
        }

        return redirect('/');
    }

    private function pullInvitedHousehold(): ?Household
    {
        $code = session()->pull('pending_invite_code');
        if (! $code) {
            return null;
        }

        return Household::where('invite_code', strtoupper($code))->first();
    }
}
