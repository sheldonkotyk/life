<?php

namespace App\Http\Controllers;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AppleNotificationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->input('payload');

        if (! is_string($token) || $token === '') {
            Log::warning('apple.notification.missing_payload', ['body' => $request->all()]);
            return response()->json(['ok' => false], 400);
        }

        try {
            $keys = Cache::remember('apple.auth.keys', now()->addHour(), function () {
                $response = Http::timeout(5)->get('https://appleid.apple.com/auth/keys');
                $response->throw();
                return $response->json();
            });

            $decoded = JWT::decode($token, JWK::parseKeySet($keys));
        } catch (\Throwable $e) {
            Log::warning('apple.notification.invalid_jwt', ['error' => $e->getMessage()]);
            return response()->json(['ok' => false], 401);
        }

        $clientId = config('services.apple.client_id');
        $aud = is_array($decoded->aud ?? null) ? $decoded->aud : [$decoded->aud ?? null];

        if (($decoded->iss ?? null) !== 'https://appleid.apple.com' || ! in_array($clientId, $aud, true)) {
            Log::warning('apple.notification.bad_claims', [
                'iss' => $decoded->iss ?? null,
                'aud' => $decoded->aud ?? null,
            ]);
            return response()->json(['ok' => false], 401);
        }

        $events = json_decode($decoded->events ?? '{}', true) ?: [];

        Log::info('apple.notification.received', [
            'type' => $events['type'] ?? null,
            'sub' => $events['sub'] ?? null,
            'email' => $events['email'] ?? null,
            'event_time' => $events['event_time'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }
}
