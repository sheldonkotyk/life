<?php

namespace App\Http\Controllers;

use App\Models\BookingPage;
use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarToken;
use App\TokenProvider;
use CleaniqueCoders\TokenVault\Enums\Type;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleCalendarController extends Controller
{
    /** @var list<string> */
    private const SCOPES = [
        'https://www.googleapis.com/auth/calendar.calendarlist.readonly',
        'https://www.googleapis.com/auth/calendar.freebusy',
        'https://www.googleapis.com/auth/calendar.events',
    ];

    public function redirect(): RedirectResponse
    {
        if (blank(config('services.google.client_id')) || blank(config('services.google.client_secret'))) {
            return redirect()->route('booking.settings')->withErrors([
                'google' => 'Google Calendar is not configured yet.',
            ]);
        }

        return Socialite::driver('google')
            ->scopes(self::SCOPES)
            ->with([
                'access_type' => 'offline',
                'prompt' => 'select_account consent',
                'include_granted_scopes' => 'true',
            ])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()->route('booking.settings')->withErrors([
                'google' => 'Google Calendar access was not granted.',
            ]);
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('booking.settings')->withErrors([
                'google' => 'Google Calendar could not be connected. Please try again.',
            ]);
        }

        $user = $request->user();
        $connection = GoogleCalendarConnection::updateOrCreate(
            [
                'user_id' => $user->id,
                'google_user_id' => $googleUser->getId(),
            ],
            [
                'google_email' => $googleUser->getEmail() ?: $user->email,
            ],
        );

        $existingToken = $connection->oauthToken;
        $refreshToken = $googleUser->refreshToken ?: $existingToken?->refreshToken();
        $scopes = $googleUser->approvedScopes ?: $existingToken?->scopes() ?: self::SCOPES;
        $token = $existingToken ?? new GoogleCalendarToken;

        $token->fill([
            'provider' => TokenProvider::Google,
            'type' => Type::OAuthToken,
            'expires_at' => $googleUser->expiresIn
                ? now()->addSeconds((int) $googleUser->expiresIn)
                : null,
        ]);
        $token->tokenable()->associate($connection);
        $token->setCredentials($googleUser->token, $refreshToken, $scopes);
        $token->save();

        BookingPage::firstOrCreate(
            ['user_id' => $user->id],
            [
                'slug' => BookingPage::uniqueSlugFor($user),
                'title' => 'Meet with '.$user->name,
                'timezone' => $user->getTimezone(),
                'available_days' => [1, 2, 3, 4, 5],
            ],
        );

        return redirect()->route('booking.settings')
            ->with('status', 'Google Calendar connected. Choose which calendars to use below.');
    }
}
