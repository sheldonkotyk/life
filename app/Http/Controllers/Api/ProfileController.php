<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\User;
use App\Support\ApiPayload;
use App\Support\Avatar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The signed-in person: their own settings, and which household they are in.
 */
class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(ApiPayload::user($request->user()->load('familyMember')));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'timezone' => ['sometimes', 'timezone'],
            'birthday' => ['nullable', 'date'],
            'notification_preferences' => ['sometimes', 'array'],
            'notification_preferences.*' => ['boolean'],
            'daily_today_email_at' => ['nullable', 'date_format:H:i'],
            'booking_emails_enabled' => ['sometimes', 'boolean'],
            'avatar_config' => ['sometimes', 'nullable', 'array'],
        ]);

        $user = $request->user();
        $changes = collect($data)->except(['notification_preferences', 'daily_today_email_at', 'avatar_config'])->all();

        if (array_key_exists('notification_preferences', $data)) {
            $prefs = [];
            foreach (User::NOTIFICATION_CHANNELS as $channel) {
                $prefs[$channel] = (bool) ($data['notification_preferences'][$channel] ?? false);
            }
            $changes['notification_preferences'] = $prefs;
        }

        // Clearing the time is how the digest is switched off, so the enabled
        // flag follows the time rather than being set separately.
        if (array_key_exists('daily_today_email_at', $data)) {
            $time = $data['daily_today_email_at'];
            $changes['daily_today_email_at'] = $time ?: null;
            $changes['daily_today_email_enabled'] = (bool) $time;
            $changes['daily_today_email_last_sent_on'] = null;
        }

        if (array_key_exists('avatar_config', $data)) {
            $changes['avatar_config'] = $data['avatar_config']
                ? Avatar::normalize($data['avatar_config'])
                : null;
        }

        $user->update($changes);

        return response()->json(ApiPayload::user($user->fresh()->load('familyMember')));
    }

    public function households(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(
            $user->households()->orderBy('households.name')->get()
                ->map(fn (Household $household) => ApiPayload::household($household, $user))
                ->values()
        );
    }

    public function createHousehold(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);

        $user = $request->user();
        $household = Household::create(['name' => trim($data['name'])]);
        $user->joinHousehold($household);

        return response()->json(ApiPayload::household($household->fresh(), $user->fresh()), 201);
    }

    public function joinHousehold(Request $request): JsonResponse
    {
        $data = $request->validate(['invite_code' => ['required', 'string', 'max:12']]);

        $user = $request->user();
        $household = Household::where('invite_code', mb_strtoupper(trim($data['invite_code'])))->first();

        if (! $household) {
            throw ValidationException::withMessages(['invite_code' => 'No household found for that code.']);
        }

        if ($user->households()->where('households.id', $household->id)->exists()) {
            throw ValidationException::withMessages(['invite_code' => 'You are already in that household.']);
        }

        $user->joinHousehold($household);

        return response()->json(ApiPayload::household($household->fresh(), $user->fresh()));
    }

    public function switchHousehold(Request $request, Household $household): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->households()->where('households.id', $household->id)->exists(), 403);

        $user->forceFill(['household_id' => $household->id])->save();

        return response()->json(ApiPayload::household($household, $user->fresh()));
    }

    /**
     * Leave a household, taking care not to strand it without an administrator.
     */
    public function leaveHousehold(Request $request, Household $household): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->households()->where('households.id', $household->id)->exists(), 403);

        $othersRemain = $household->users()->where('users.id', '!=', $user->id)->exists();

        if ($user->isAdminOf($household)
            && $household->admins()->where('users.id', '!=', $user->id)->doesntExist()
            && $othersRemain
        ) {
            throw ValidationException::withMessages([
                'household' => 'Promote another admin before leaving '.$household->name.'.',
            ]);
        }

        $household->users()->detach($user->id);

        if (! $othersRemain) {
            $household->delete();
        }

        if ($user->household_id === $household->id) {
            $this->moveToFallbackHousehold($user);
        }

        return response()->json(ApiPayload::user($user->fresh()->load('familyMember')));
    }

    private function moveToFallbackHousehold(User $user): void
    {
        $next = $user->households()->orderBy('households.id')->first();

        if (! $next) {
            $next = Household::create(['name' => ($user->name ?: 'Your')."'s Household"]);
            $user->joinHousehold($next);

            return;
        }

        $user->forceFill(['household_id' => $next->id])->save();
    }
}
