<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimezoneController extends Controller
{
    public function detect(Request $request): JsonResponse
    {
        $data = $request->validate([
            'timezone' => ['required', 'timezone'],
        ]);

        $user = $request->user();
        if (! $user->timezone) {
            $user->update(['timezone' => $data['timezone']]);
        }

        return response()->json(['timezone' => $user->getTimezone()]);
    }
}
