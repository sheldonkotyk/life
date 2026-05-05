<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ApiAuthController extends Controller
{
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
        $user->email = $data['email'] ?? $user->email ?? ($sub . '@apple.private');
        $user->name = $data['name'] ?? $user->name ?? 'Apple User';
        if (isset($data['timezone']) && ! $user->timezone) {
            $user->timezone = $data['timezone'];
        }

        $newHousehold = null;
        if (! $user->household_id) {
            $newHousehold = Household::create(['name' => $user->name . "'s Household"]);
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
