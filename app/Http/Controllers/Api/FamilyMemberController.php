<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FamilyMember;
use App\Models\FoodPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyMemberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            FamilyMember::where('household_id', $request->user()->household_id)
                ->with('preferences')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:7'],
            'is_child' => ['boolean'],
            'birthday' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $data['household_id'] = $request->user()->household_id;
        return response()->json(FamilyMember::create($data), 201);
    }

    public function update(Request $request, FamilyMember $member): JsonResponse
    {
        $this->authorizeMember($request, $member);
        $member->update($request->validate([
            'name' => ['sometimes', 'string', 'max:60'],
            'color' => ['sometimes', 'string', 'max:7'],
            'is_child' => ['sometimes', 'boolean'],
            'birthday' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]));
        return response()->json($member);
    }

    public function destroy(Request $request, FamilyMember $member): JsonResponse
    {
        $this->authorizeMember($request, $member);
        $member->delete();
        return response()->json(['ok' => true]);
    }

    public function addPreference(Request $request, FamilyMember $member): JsonResponse
    {
        $this->authorizeMember($request, $member);
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
        $this->authorizeMember($request, $preference->familyMember);
        $preference->delete();
        return response()->json(['ok' => true]);
    }

    private function authorizeMember(Request $request, FamilyMember $member): void
    {
        abort_unless($member->household_id === $request->user()->household_id, 403);
    }
}
