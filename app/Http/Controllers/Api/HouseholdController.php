<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FamilyMember;
use App\Models\FoodPreference;
use App\Models\Household;
use App\Models\User;
use App\Support\ApiPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HouseholdController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $household = $user->household()->firstOrFail();

        $members = FamilyMember::where('household_id', $household->id)
            ->with('preferences')
            ->orderBy('is_guest')
            ->orderBy('is_child')
            ->orderBy('name')
            ->get();

        $adminIds = $household->admins()->pluck('users.id')->all();

        return response()->json([
            ...ApiPayload::household($household, $user),
            'dismissed_meal_names' => $household->dismissed_meal_names ?? [],
            'members' => $members->map(fn (FamilyMember $member) => [
                ...ApiPayload::member($member),
                'preferences' => $member->preferences->map(fn (FoodPreference $preference) => [
                    'id' => $preference->id,
                    'food' => $preference->food,
                    'type' => $preference->type,
                    'notes' => $preference->notes,
                ])->values()->all(),
            ])->all(),
            'users' => $household->users()->orderBy('name')->get()->map(function (User $member) use ($adminIds) {
                $stored = $member->getRawOriginal('avatar');

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'is_admin' => in_array($member->id, $adminIds, true),
                    'avatar_config' => $member->avatar_config,
                    'avatar_url' => $stored
                        ? (str_starts_with($stored, 'http') ? $stored : Storage::disk('public')->url($stored))
                        : null,
                ];
            })->values()->all(),
        ]);
    }

    public function updateName(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $this->authorizeManage($request);
        $request->user()->household()->update($data);

        return response()->json(['ok' => true]);
    }

    /**
     * When each meal is normally eaten. A plan without its own time falls back
     * to these, which is what puts an unscheduled dinner on the calendar.
     */
    public function updateMealTimes(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $data = $request->validate([
            'breakfast_start_time' => ['required', 'date_format:H:i'],
            'breakfast_end_time' => ['required', 'date_format:H:i', 'after:breakfast_start_time'],
            'lunch_start_time' => ['required', 'date_format:H:i'],
            'lunch_end_time' => ['required', 'date_format:H:i', 'after:lunch_start_time'],
            'dinner_start_time' => ['required', 'date_format:H:i'],
            'dinner_end_time' => ['required', 'date_format:H:i', 'after:dinner_start_time'],
        ]);

        $household = $request->user()->household;
        $household->update($data);

        return response()->json(ApiPayload::household($household->fresh(), $request->user()));
    }

    public function rotateInvite(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $household = $request->user()->household;
        $household->invite_code = mb_strtoupper(Str::random(8));
        $household->save();

        return response()->json(['invite_code' => $household->invite_code]);
    }

    public function join(Request $request): JsonResponse
    {
        $data = $request->validate(['invite_code' => ['required', 'string']]);
        $household = Household::where('invite_code', mb_strtoupper($data['invite_code']))->firstOrFail();
        $request->user()->joinHousehold($household);

        return response()->json(['ok' => true, 'household_id' => $household->id]);
    }

    public function makeAdmin(Request $request, User $user): JsonResponse
    {
        $household = $this->authorizeManage($request);
        abort_unless($household->users()->where('users.id', $user->id)->exists(), 404);

        $household->users()->updateExistingPivot($user->id, ['role' => 'admin']);

        return response()->json(['ok' => true]);
    }

    public function removeAdmin(Request $request, User $user): JsonResponse
    {
        $household = $this->authorizeManage($request);
        abort_unless($household->users()->where('users.id', $user->id)->exists(), 404);

        if ($household->admins()->count() <= 1 && $household->admins()->where('users.id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'user' => 'A household must have at least one administrator.',
            ]);
        }

        $household->users()->updateExistingPivot($user->id, ['role' => null]);

        return response()->json(['ok' => true]);
    }

    /**
     * Stop offering a one-off meal name as a recipe worth saving.
     */
    public function dismissMealName(Request $request): JsonResponse
    {
        $data = $request->validate(['custom_name' => ['required', 'string', 'max:120']]);

        $household = $request->user()->household()->firstOrFail();
        $dismissed = $household->dismissed_meal_names ?? [];

        if (! in_array($data['custom_name'], $dismissed, true)) {
            $dismissed[] = $data['custom_name'];
            $household->update(['dismissed_meal_names' => $dismissed]);
        }

        return response()->json(['dismissed_meal_names' => $dismissed]);
    }

    public function restoreDismissedMealNames(Request $request): JsonResponse
    {
        $request->user()->household()->update(['dismissed_meal_names' => null]);

        return response()->json(['dismissed_meal_names' => []]);
    }

    private function authorizeManage(Request $request): Household
    {
        $household = $request->user()->household;
        abort_unless($household && $request->user()->canManageHousehold($household), 403);

        return $household;
    }
}
