<?php

namespace App\Services;

use App\Contracts\GoogleCalendar;
use App\Models\Booking;
use App\Models\BookingCalendarSelection;
use App\Models\BookingPage;
use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarToken;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class GoogleCalendarClient implements GoogleCalendar
{
    private const API_URL = 'https://www.googleapis.com/calendar/v3';

    /**
     * @return list<array{id: string, name: string, primary: bool, access_role: string}>
     */
    public function calendars(GoogleCalendarConnection $connection): array
    {
        $calendars = [];
        $pageToken = null;

        do {
            $query = ['maxResults' => 250];
            if ($pageToken) {
                $query['pageToken'] = $pageToken;
            }

            $response = $this->send(
                $connection,
                fn (PendingRequest $request): Response => $request->get(self::API_URL.'/users/me/calendarList', $query),
            )->throw();

            foreach ($response->json('items', []) as $calendar) {
                if (! isset($calendar['id'], $calendar['summary'])) {
                    continue;
                }

                $calendars[] = [
                    'id' => (string) $calendar['id'],
                    'name' => (string) $calendar['summary'],
                    'primary' => (bool) ($calendar['primary'] ?? false),
                    'access_role' => (string) ($calendar['accessRole'] ?? 'reader'),
                ];
            }

            $pageToken = $response->json('nextPageToken');
        } while ($pageToken);

        return $calendars;
    }

    /**
     * @param  list<string>  $calendarIds
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    public function busyPeriods(
        GoogleCalendarConnection $connection,
        array $calendarIds,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        string $timezone,
    ): array {
        $response = $this->send(
            $connection,
            fn (PendingRequest $request): Response => $request->post(self::API_URL.'/freeBusy', [
                'timeMin' => $startsAt->utc()->toRfc3339String(),
                'timeMax' => $endsAt->utc()->toRfc3339String(),
                'timeZone' => $timezone,
                'calendarExpansionMax' => 50,
                'items' => array_map(fn (string $id): array => ['id' => $id], $calendarIds),
            ]),
        )->throw();

        $calendarResults = $response->json('calendars', []);
        if (! is_array($calendarResults) || collect($calendarIds)->diff(array_keys($calendarResults))->isNotEmpty()) {
            throw new RuntimeException('Google did not return availability for every selected calendar.');
        }

        $periods = [];
        foreach ($calendarResults as $calendar) {
            if (! empty($calendar['errors'])) {
                throw new RuntimeException('Google could not read one of the selected calendars.');
            }

            foreach ($calendar['busy'] ?? [] as $period) {
                if (isset($period['start'], $period['end'])) {
                    $periods[] = [
                        'start' => CarbonImmutable::parse($period['start']),
                        'end' => CarbonImmutable::parse($period['end']),
                    ];
                }
            }
        }

        return $periods;
    }

    /**
     * @return array{id: string, html_link: string|null}
     */
    public function createEvent(
        BookingCalendarSelection $calendar,
        BookingPage $bookingPage,
        Booking $booking,
    ): array {
        $calendar->loadMissing('connection.oauthToken');
        $connection = $calendar->connection;
        $eventId = 'lifebooking'.$booking->id;
        $url = self::API_URL.'/calendars/'.rawurlencode($calendar->google_calendar_id).'/events';

        $response = $this->send(
            $connection,
            fn (PendingRequest $request): Response => $request->withQueryParameters(['sendUpdates' => 'all'])->post($url, [
                'id' => $eventId,
                'summary' => $booking->summary(),
                'description' => $this->eventDescription($booking),
                'start' => [
                    'dateTime' => $booking->starts_at->toRfc3339String(),
                    'timeZone' => $bookingPage->timezone,
                ],
                'end' => [
                    'dateTime' => $booking->ends_at->toRfc3339String(),
                    'timeZone' => $bookingPage->timezone,
                ],
                'attendees' => [[
                    'email' => $booking->guest_email,
                    'displayName' => $booking->guest_name,
                ]],
            ]),
        );

        if ($response->status() === 409) {
            $response = $this->send(
                $connection,
                fn (PendingRequest $request): Response => $request->get($url.'/'.$eventId),
            );
        }

        $response->throw();

        return [
            'id' => (string) $response->json('id', $eventId),
            'html_link' => $response->json('htmlLink'),
        ];
    }

    public function updateEventTime(
        GoogleCalendarConnection $connection,
        string $calendarId,
        string $eventId,
        Booking $booking,
        string $timezone,
    ): void {
        $this->send(
            $connection,
            fn (PendingRequest $request): Response => $request
                ->withQueryParameters(['sendUpdates' => 'all'])
                ->patch(self::API_URL.'/calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($eventId), [
                    'start' => [
                        'dateTime' => $booking->starts_at->toRfc3339String(),
                        'timeZone' => $timezone,
                    ],
                    'end' => [
                        'dateTime' => $booking->ends_at->toRfc3339String(),
                        'timeZone' => $timezone,
                    ],
                ]),
        )->throw();
    }

    public function deleteEvent(
        GoogleCalendarConnection $connection,
        string $calendarId,
        string $eventId,
    ): void {
        $response = $this->send(
            $connection,
            fn (PendingRequest $request): Response => $request
                ->withQueryParameters(['sendUpdates' => 'all'])
                ->delete(self::API_URL.'/calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($eventId)),
        );

        // Google answers 404/410 when the host already removed the event, which
        // leaves the calendar in the state the cancellation was asking for.
        if (in_array($response->status(), [404, 410], true)) {
            return;
        }

        $response->throw();
    }

    public function revoke(GoogleCalendarConnection $connection): void
    {
        $token = $this->credential($connection);

        Http::asForm()
            ->connectTimeout(5)
            ->timeout(10)
            ->post('https://oauth2.googleapis.com/revoke', [
                'token' => $token->refreshToken() ?: $token->accessToken(),
            ])
            ->throw();
    }

    private function send(GoogleCalendarConnection $connection, callable $callback): Response
    {
        $response = $callback($this->requestWithToken($this->accessToken($connection)));

        if ($response->status() === 401 && filled($this->credential($connection)->refreshToken())) {
            $response = $callback($this->requestWithToken($this->refreshAccessToken($connection)));
        }

        return $response;
    }

    private function requestWithToken(string $token): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken($token)
            ->connectTimeout(5)
            ->timeout(15)
            ->retry(
                [100, 300],
                fn (Throwable $exception): bool => $exception instanceof ConnectionException,
                throw: false,
            );
    }

    private function accessToken(GoogleCalendarConnection $connection): string
    {
        $token = $this->credential($connection);

        if (! $token->expires_at || $token->expires_at->isAfter(now()->addMinute())) {
            return $token->accessToken();
        }

        return $this->refreshAccessToken($connection);
    }

    private function refreshAccessToken(GoogleCalendarConnection $connection): string
    {
        $token = $this->credential($connection);
        $refreshToken = $token->refreshToken();

        if (blank($refreshToken)) {
            throw new RuntimeException('Google Calendar needs to be reconnected.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(10)
            ->retry(
                [100, 300],
                fn (Throwable $exception): bool => $exception instanceof ConnectionException,
                throw: false,
            )
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ])
            ->throw();

        $accessToken = $response->json('access_token');
        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Google did not return a new access token.');
        }

        $token->setCredentials($accessToken, $refreshToken, $token->scopes());
        $token->expires_at = now()->addSeconds((int) $response->json('expires_in', 3600));
        $token->save();

        return $accessToken;
    }

    private function credential(GoogleCalendarConnection $connection): GoogleCalendarToken
    {
        $connection->loadMissing('oauthToken');

        return $connection->oauthToken
            ?? throw new RuntimeException('Google Calendar needs to be reconnected.');
    }

    private function eventDescription(Booking $booking): string
    {
        $description = "Requested through Life by {$booking->guest_name} ({$booking->guest_email}).";

        if (filled($booking->notes)) {
            $description .= "\n\nNotes:\n{$booking->notes}";
        }

        return $description
            ."\n\nNeed a different time? ".$booking->rescheduleUrl()
            ."\nNeed to cancel? ".$booking->cancelUrl();
    }
}
