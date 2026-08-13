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
     * @return array{id: string, html_link: string|null, ical_uid: string|null}
     */
    public function createEvent(
        BookingCalendarSelection $calendar,
        BookingPage $bookingPage,
        Booking $booking,
        bool $awaitingApproval = false,
    ): array {
        $calendar->loadMissing('connection.oauthToken');
        $connection = $calendar->connection;
        $eventId = 'lifebooking'.$booking->id;
        $url = self::API_URL.'/calendars/'.rawurlencode($calendar->google_calendar_id).'/events';

        // A request is written as tentative with no attendee: the owner sees it
        // pencilled into their agenda with the links to answer it, and the guest
        // is only invited once it is accepted. Attendees can read the
        // description, so the answer links must not travel with it.
        $body = [
            'id' => $eventId,
            'summary' => $awaitingApproval ? 'Request: '.$booking->summary() : $booking->summary(),
            'description' => $awaitingApproval
                ? $this->requestDescription($booking)
                : $this->eventDescription($booking),
            'status' => $awaitingApproval ? 'tentative' : 'confirmed',
            'start' => [
                'dateTime' => $booking->starts_at->toRfc3339String(),
                'timeZone' => $bookingPage->timezone,
            ],
            'end' => [
                'dateTime' => $booking->ends_at->toRfc3339String(),
                'timeZone' => $bookingPage->timezone,
            ],
        ];

        if (! $awaitingApproval) {
            $body['attendees'] = [[
                'email' => $booking->guest_email,
                'displayName' => $booking->guest_name,
            ]];
        }

        $response = $this->send(
            $connection,
            fn (PendingRequest $request): Response => $request
                ->withQueryParameters(['sendUpdates' => $awaitingApproval ? 'none' : 'all'])
                ->post($url, $body),
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
            'ical_uid' => $response->json('iCalUID'),
        ];
    }

    /**
     * Promote a tentative request to a confirmed meeting and invite the guest.
     */
    public function confirmEvent(
        GoogleCalendarConnection $connection,
        string $calendarId,
        string $eventId,
        Booking $booking,
    ): void {
        $this->send(
            $connection,
            fn (PendingRequest $request): Response => $request
                ->withQueryParameters(['sendUpdates' => 'all'])
                ->patch(self::API_URL.'/calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($eventId), [
                    'status' => 'confirmed',
                    'summary' => $booking->summary(),
                    'description' => $this->eventDescription($booking),
                    'attendees' => [[
                        'email' => $booking->guest_email,
                        'displayName' => $booking->guest_name,
                    ]],
                ]),
        )->throw();
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

    /**
     * Events on these calendars within a window, for showing an agenda.
     *
     * @param  list<string>  $calendarIds
     * @return list<array{id: string, calendar_id: string, title: string, starts_at: CarbonImmutable, ends_at: CarbonImmutable, all_day: bool, link: string|null}>
     */
    public function eventsBetween(
        GoogleCalendarConnection $connection,
        array $calendarIds,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        string $timezone,
    ): array {
        $events = [];

        foreach ($calendarIds as $calendarId) {
            $response = $this->send(
                $connection,
                fn (PendingRequest $request): Response => $request->get(
                    self::API_URL.'/calendars/'.rawurlencode($calendarId).'/events',
                    [
                        'timeMin' => $startsAt->utc()->toRfc3339String(),
                        'timeMax' => $endsAt->utc()->toRfc3339String(),
                        'timeZone' => $timezone,
                        'singleEvents' => 'true',
                        'orderBy' => 'startTime',
                        'maxResults' => 100,
                    ],
                ),
            )->throw();

            foreach ($response->json('items', []) as $event) {
                if (($event['status'] ?? null) === 'cancelled') {
                    continue;
                }

                $start = $event['start']['dateTime'] ?? $event['start']['date'] ?? null;
                $end = $event['end']['dateTime'] ?? $event['end']['date'] ?? null;

                if (! is_string($start) || ! is_string($end)) {
                    continue;
                }

                $allDay = ! isset($event['start']['dateTime']);

                $events[] = [
                    'id' => (string) ($event['id'] ?? ''),
                    'calendar_id' => $calendarId,
                    'title' => (string) ($event['summary'] ?? 'Busy'),
                    'starts_at' => CarbonImmutable::parse($start, $timezone),
                    'ends_at' => CarbonImmutable::parse($end, $timezone),
                    'all_day' => $allDay,
                    'link' => $event['htmlLink'] ?? null,
                    // Google models working location, focus time and
                    // out-of-office as events of their own kind.
                    'type' => (string) ($event['eventType'] ?? 'default'),
                    'busy' => ($event['transparency'] ?? 'opaque') === 'opaque',
                    'location' => $event['location'] ?? null,
                    'description' => $event['description'] ?? null,
                    'organizer' => $event['organizer']['email'] ?? null,
                    'attendees' => $this->attendees($event),
                ];
            }
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return list<array{name: string, email: string, status: string, organizer: bool, self: bool}>
     */
    private function attendees(array $event): array
    {
        $attendees = [];

        foreach ($event['attendees'] ?? [] as $attendee) {
            if ($attendee['resource'] ?? false) {
                continue;
            }

            $email = (string) ($attendee['email'] ?? '');

            $attendees[] = [
                'name' => (string) ($attendee['displayName'] ?? $email),
                'email' => $email,
                'status' => (string) ($attendee['responseStatus'] ?? 'needsAction'),
                'organizer' => (bool) ($attendee['organizer'] ?? false),
                'self' => (bool) ($attendee['self'] ?? false),
            ];
        }

        return $attendees;
    }

    /**
     * Events changed since the last poll. Without a token this asks for
     * everything from yesterday onward and returns a token to continue from.
     *
     * @return array{events: list<array<string, mixed>>, sync_token: string|null, expired: bool}
     */
    public function changedEvents(
        GoogleCalendarConnection $connection,
        string $calendarId,
        ?string $syncToken = null,
    ): array {
        $url = self::API_URL.'/calendars/'.rawurlencode($calendarId).'/events';
        $events = [];
        $pageToken = null;
        $nextSyncToken = null;

        do {
            // Google rejects a sync token whose other parameters changed, so
            // the shape of this query has to stay identical between runs.
            $query = ['showDeleted' => 'true', 'singleEvents' => 'true', 'maxResults' => 250];
            $query += $syncToken
                ? ['syncToken' => $syncToken]
                : ['timeMin' => now()->subDay()->utc()->toRfc3339String()];

            if ($pageToken) {
                $query['pageToken'] = $pageToken;
            }

            $response = $this->send(
                $connection,
                fn (PendingRequest $request): Response => $request->get($url, $query),
            );

            // A token Google has forgotten: the caller starts over from scratch.
            if ($response->status() === 410) {
                return ['events' => [], 'sync_token' => null, 'expired' => true];
            }

            $response->throw();

            foreach ($response->json('items', []) as $event) {
                $events[] = $event;
            }

            $pageToken = $response->json('nextPageToken');
            $nextSyncToken = $response->json('nextSyncToken') ?: $nextSyncToken;
        } while ($pageToken);

        return ['events' => $events, 'sync_token' => $nextSyncToken, 'expired' => false];
    }

    /**
     * Ask Google to notify a URL when this calendar changes. The notification
     * carries no payload, so it only ever means "come and look".
     *
     * @return array{resource_id: string, expires_at: CarbonImmutable|null}
     */
    public function watchEvents(
        GoogleCalendarConnection $connection,
        string $calendarId,
        string $channelId,
        string $address,
        string $token,
        int $ttlSeconds,
    ): array {
        $response = $this->send(
            $connection,
            fn (PendingRequest $request): Response => $request->post(
                self::API_URL.'/calendars/'.rawurlencode($calendarId).'/events/watch',
                [
                    'id' => $channelId,
                    'type' => 'web_hook',
                    'address' => $address,
                    'token' => $token,
                    'params' => ['ttl' => (string) $ttlSeconds],
                ],
            ),
        )->throw();

        $expiration = $response->json('expiration');

        return [
            'resource_id' => (string) $response->json('resourceId'),
            'expires_at' => $expiration
                ? CarbonImmutable::createFromTimestampMs((int) $expiration)
                : null,
        ];
    }

    public function stopWatch(
        GoogleCalendarConnection $connection,
        string $channelId,
        string $resourceId,
    ): void {
        $response = $this->send(
            $connection,
            fn (PendingRequest $request): Response => $request->post(self::API_URL.'/channels/stop', [
                'id' => $channelId,
                'resourceId' => $resourceId,
            ]),
        );

        // A channel Google has already forgotten is in the state we wanted.
        if (in_array($response->status(), [404, 410], true)) {
            return;
        }

        $response->throw();
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

    /**
     * What the owner reads in their agenda while the request waits.
     */
    private function requestDescription(Booking $booking): string
    {
        $description = "{$booking->guest_name} ({$booking->guest_email}) has requested a meeting with you.";

        if (filled($booking->notes)) {
            $description .= "\n\n{$booking->notes}";
        }

        return $description
            ."\n\nAccept: ".$booking->acceptUrl()
            ."\nDecline: ".$booking->declineUrl()
            ."\nSuggest another time: ".$booking->rescheduleUrl();
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
