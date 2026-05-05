<?php

namespace App\Http\Controllers;

use App\Mail\MagicLoginLink;
use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\MagicLoginToken;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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
            'pendingCodeEmail' => session('magic_pending_email'),
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

    public function joinViaLink(Request $request, string $code): RedirectResponse
    {
        $code = strtoupper(trim($code));
        $household = Household::where('invite_code', $code)->first();

        if (! $household) {
            return redirect()->route('login')->withErrors([
                'invite_code' => 'No household found for that invite link.',
            ]);
        }

        if ($user = $request->user()) {
            $user->joinHousehold($household);
            return redirect('/')->with('status', 'You joined ' . $household->name . '.');
        }

        $request->session()->put('pending_invite_code', $code);

        return redirect()->route('login')->with(
            'status',
            'You\'ll join ' . $household->name . ' after signing in.'
        );
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

    public function requestMagicLink(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($data['email']));

        $key = 'magic-link:' . sha1($email . '|' . $request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors([
                'email' => 'Too many attempts. Please wait a minute and try again.',
            ])->withInput();
        }
        RateLimiter::hit($key, 60);

        $token = Str::random(48);
        $code = (string) random_int(100000, 999999);
        $minutes = 15;

        MagicLoginToken::where('email', $email)->whereNull('used_at')->delete();

        MagicLoginToken::create([
            'email' => $email,
            'token_hash' => hash('sha256', $token),
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes($minutes),
        ]);

        $url = url('/auth/magic/' . $token);

        Mail::to($email)->send(new MagicLoginLink($url, $code, $minutes));

        $request->session()->put('magic_pending_email', $email);

        return back()->with('status', 'Check your email — we sent you a sign-in link and a code.');
    }

    public function verifyMagicCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $email = $request->session()->get('magic_pending_email');

        if (! $email) {
            return redirect('/login')->withErrors([
                'code' => 'Request a new sign-in code first.',
            ]);
        }

        $key = 'magic-code:' . sha1($email . '|' . $request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors([
                'code' => 'Too many attempts. Request a new code.',
            ]);
        }
        RateLimiter::hit($key, 60);

        $codeHash = hash('sha256', $data['code']);

        $record = MagicLoginToken::where('email', $email)
            ->where('code_hash', $codeHash)
            ->whereNull('used_at')
            ->first();

        if (! $record || $record->expires_at->isPast()) {
            return back()->withErrors([
                'code' => 'That code is invalid or has expired.',
            ]);
        }

        $record->update(['used_at' => now()]);
        $request->session()->forget('magic_pending_email');

        return $this->finishMagicLogin($email);
    }

    public function magicCallback(Request $request, string $token): RedirectResponse
    {
        $hash = hash('sha256', $token);

        $record = MagicLoginToken::where('token_hash', $hash)->first();

        if (! $record || $record->used_at || $record->expires_at->isPast()) {
            return redirect('/login')->withErrors([
                'email' => 'That sign-in link is invalid or has expired. Request a new one.',
            ]);
        }

        $record->update(['used_at' => now()]);
        $request->session()->forget('magic_pending_email');

        return $this->finishMagicLogin($record->email);
    }

    private function finishMagicLogin(string $email): RedirectResponse
    {
        $user = User::firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->name = Str::headline(Str::before($email, '@')) ?: 'You';
        }

        $invitedHousehold = $this->pullInvitedHousehold();

        $newHousehold = null;
        if (! $user->household_id && ! $invitedHousehold) {
            $householdName = $user->name ? $user->name . "'s Household" : 'Your Household';
            $newHousehold = Household::create(['name' => $householdName]);
        }

        $user->email_verified_at = $user->email_verified_at ?: now();
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
