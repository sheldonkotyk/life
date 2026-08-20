<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FamilyMember;
use App\Models\FoodPreference;
use App\Support\ApiPayload;
use App\Support\Avatar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FamilyMemberController extends Controller
{
    private const DAYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    private const SLOTS = ['breakfast', 'lunch', 'dinner'];

    public function index(Request $request): JsonResponse
    {
        $members = FamilyMember::where('household_id', $request->user()->household_id)
            ->with('preferences')
            ->orderBy('is_guest')
            ->orderBy('is_child')
            ->orderBy('name')
            ->get();

        return response()->json($members->map(fn (FamilyMember $member) => $this->payload($member)));
    }

    public function show(Request $request, FamilyMember $member): JsonResponse
    {
        $this->authorizeMember($request, $member);

        return response()->json($this->payload($member->load('preferences')));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:7'],
            'is_child' => ['boolean'],
            'is_guest' => ['boolean'],
            'birthday' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $data['household_id'] = $request->user()->household_id;

        return response()->json($this->payload(FamilyMember::create($data)), 201);
    }

    public function update(Request $request, FamilyMember $member): JsonResponse
    {
        $this->authorizeEdit($request, $member);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:60'],
            'color' => ['sometimes', 'string', 'max:7'],
            'is_child' => ['sometimes', 'boolean'],
            'is_guest' => ['sometimes', 'boolean'],
            'birthday' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'avatar_config' => ['sometimes', 'nullable', 'array'],
            'target_calories' => ['nullable', 'numeric', 'min:0'],
            'target_protein_g' => ['nullable', 'numeric', 'min:0'],
            'target_carbs_g' => ['nullable', 'numeric', 'min:0'],
            'target_fat_g' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (array_key_exists('avatar_config', $data)) {
            $data['avatar_config'] = $data['avatar_config'] ? Avatar::normalize($data['avatar_config']) : null;
        }

        // A member who is also an account keeps the two names in step, the way
        // saving the web profile does.
        if (isset($data['name']) && $member->user_id) {
            $member->user?->update(['name' => $data['name']]);
        }

        $member->update($data);

        return response()->json($this->payload($member->fresh()->load('preferences')));
    }

    public function destroy(Request $request, FamilyMember $member): JsonResponse
    {
        $this->authorizeEdit($request, $member);
        abort_if($member->user_id, 403);
        $member->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Which meals this person normally turns up to, one cell at a time.
     */
    public function setDefaultAttendance(Request $request, FamilyMember $member): JsonResponse
    {
        $this->authorizeEdit($request, $member);

        $data = $request->validate([
            'attending' => ['required', 'boolean'],
            'days' => ['sometimes', 'array'],
            'days.*' => [Rule::in(self::DAYS)],
            'slots' => ['sometimes', 'array'],
            'slots.*' => [Rule::in(self::SLOTS)],
        ]);

        $days = $data['days'] ?? self::DAYS;
        $slots = $data['slots'] ?? self::SLOTS;

        foreach ($days as $day) {
            foreach ($slots as $slot) {
                $member->setDefaultAttendance($day, $slot, $data['attending']);
            }
        }

        return response()->json($this->payload($member->fresh()->load('preferences')));
    }

    public function addPreference(Request $request, FamilyMember $member): JsonResponse
    {
        $this->authorizeEdit($request, $member);
        $data = $request->validate([
            'food' => ['required', 'string', 'max:80'],
            'type' => ['required', 'in:like,dislike,allergy'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $data['family_member_id'] = $member->id;

        return response()->json(FoodPreference::create($data), 201);
    }

    public function removePreference(Request $request, FoodPreference $preference): JsonResponse
    {
        $this->authorizeEdit($request, $preference->familyMember);
        $preference->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FamilyMember $member): array
    {
        $payload = ApiPayload::member($member);

        if ($member->relationLoaded('preferences')) {
            $payload['preferences'] = $member->preferences->map(fn (FoodPreference $preference) => [
                'id' => $preference->id,
                'food' => $preference->food,
                'type' => $preference->type,
                'notes' => $preference->notes,
            ])->values()->all();
        }

        return $payload;
    }

    private function authorizeMember(Request $request, FamilyMember $member): void
    {
        abort_unless($member->household_id === $request->user()->household_id, 403);
    }

    /**
     * Admins edit anyone; everyone else edits only themselves.
     */
    private function authorizeEdit(Request $request, FamilyMember $member): void
    {
        $this->authorizeMember($request, $member);

        $user = $request->user();
        abort_unless(
            ($user->household && $user->canManageHousehold($user->household)) || $member->user_id === $user->id,
            403
        );
    }
}
