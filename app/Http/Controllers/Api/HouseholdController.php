<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HouseholdController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $hh = $request->user()->household()->with([
            'members.preferences',
            'members.user:id,name,email',
            'recipes.ingredients',
            'recipes.ratings',
        ])->firstOrFail();

        return response()->json($hh);
    }

    public function updateName(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $request->user()->household()->update($data);
        return response()->json(['ok' => true]);
    }

    public function rotateInvite(Request $request): JsonResponse
    {
        $hh = $request->user()->household;
        $hh->invite_code = strtoupper(\Illuminate\Support\Str::random(8));
        $hh->save();
        return response()->json(['invite_code' => $hh->invite_code]);
    }

    public function join(Request $request): JsonResponse
    {
        $data = $request->validate(['invite_code' => ['required', 'string']]);
        $hh = \App\Models\Household::where('invite_code', strtoupper($data['invite_code']))->firstOrFail();
        $request->user()->joinHousehold($hh);
        return response()->json(['ok' => true, 'household_id' => $hh->id]);
    }
}
