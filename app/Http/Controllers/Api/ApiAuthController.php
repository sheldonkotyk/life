<?php

namespace App\Http\Controllers\Api;

use App\Actions\ProvisionUserForEmail;
use App\Http\Controllers\Controller;
use App\Mail\MagicLoginLink;
use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\MagicLoginToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApiAuthController extends Controller
{
    private const CODE_LIFETIME_MINUTES = 15;

    /**
     * Email a six digit sign-in code.
     *
     * The same token row backs the web link, so a code requested on the phone
     * can still be finished by tapping the link in the mail app.
     */
    public function requestMagicCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = mb_strtolower(trim($data['email']));

        $key = 'magic-link:'.sha1($email.'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Please wait a minute and try again.',
            ]);
        }
        RateLimiter::hit($key, 60);

        $token = Str::random(48);
        $code = (string) random_int(100000, 999999);
        $minutes = self::CODE_LIFETIME_MINUTES;

        MagicLoginToken::where('email', $email)->whereNull('used_at')->delete();

        MagicLoginToken::create([
            'email' => $email,
            'token_hash' => hash('sha256', $token),
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes($minutes),
        ]);

        Mail::to($email)->send(new MagicLoginLink(url('/auth/magic/'.$token), $code, $minutes));

        return response()->json([
            'ok' => true,
            'expires_in_minutes' => $minutes,
        ]);
    }

    /**
     * Exchange a mailed code for an API token.
     *
     * Unlike the web flow there is no session to remember which address asked,
     * so the client sends the address back with the code.
     */
    public function verifyMagicCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'string', 'size:6'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'timezone' => ['nullable', 'timezone'],
            'invite_code' => ['nullable', 'string', 'max:12'],
        ]);

        $email = mb_strtolower(trim($data['email']));

        $key = 'magic-code:'.sha1($email.'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'code' => 'Too many attempts. Request a new code.',
            ]);
        }
        RateLimiter::hit($key, 60);

        $record = MagicLoginToken::where('email', $email)
            ->where('code_hash', hash('sha256', $data['code']))
            ->whereNull('used_at')
            ->first();

        if (! $record || $record->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'code' => 'That code is invalid or has expired.',
            ]);
        }

        $record->update(['used_at' => now()]);

        $invited = isset($data['invite_code'])
            ? Household::where('invite_code', mb_strtoupper($data['invite_code']))->first()
            : null;

        $user = app(ProvisionUserForEmail::class)->execute($email, $invited);

        if (isset($data['timezone']) && ! $user->timezone) {
            $user->forceFill(['timezone' => $data['timezone']])->save();
        }

        $token = $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->load('household', 'familyMember'),
        ]);
    }

    /**
     * Exchange a verified Apple identity token for a Sanctum API token.
     *
     * The mobile client performs Sign In with Apple natively, then posts
     * the resulting identity token here. We verify it against Apple's JWKS
     * and provision/find the user.
     *
     * NOTE: full Apple JWT verification is intentionally a stub for v1.
     * Wire up firebase/php-jwt + Apple's JWKS before shipping the mobile app.
     */
    public function apple(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identity_token' => ['required', 'string'],
            'name' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'device_name' => ['nullable', 'string'],
            'timezone' => ['nullable', 'timezone'],
        ]);

        $sub = $this->extractAppleSub($data['identity_token']);
        if (! $sub) {
            throw ValidationException::withMessages(['identity_token' => 'Invalid Apple token.']);
        }

        $user = User::firstOrNew(['apple_sub' => $sub]);
        $user->email = $data['email'] ?? $user->email ?? ($sub.'@apple.private');
        $user->name = $data['name'] ?? $user->name ?? 'Apple User';
        if (isset($data['timezone']) && ! $user->timezone) {
            $user->timezone = $data['timezone'];
        }

        $newHousehold = null;
        if (! $user->household_id) {
            $newHousehold = Household::create(['name' => $user->name."'s Household"]);
        }
        $user->save();

        if ($newHousehold) {
            $user->joinHousehold($newHousehold);
        }

        if (! $user->familyMember) {
            FamilyMember::create([
                'household_id' => $user->household_id,
                'user_id' => $user->id,
                'name' => $user->name,
            ]);
        }

        $token = $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->load('household', 'familyMember'),
        ]);
    }

    public function devToken(Request $request): JsonResponse
    {
        abort_unless(app()->environment('local'), 404);

        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', $data['email'])->firstOrFail();
        $token = $user->createToken('dev')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->load('household', 'familyMember'),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->load('household.members.preferences', 'familyMember'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }

    private function extractAppleSub(string $jwt): ?string
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        return $payload['sub'] ?? null;
    }
}
