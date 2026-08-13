<?php

namespace App\Contracts;

use App\Models\Booking;
use App\Models\BookingCalendarSelection;
use App\Models\BookingPage;
use App\Models\GoogleCalendarConnection;
use Carbon\CarbonImmutable;

interface GoogleCalendar
{
    /**
     * @return list<array{id: string, name: string, primary: bool, access_role: string}>
     */
    public function calendars(GoogleCalendarConnection $connection): array;

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
    ): array;

    /**
     * @return array{id: string, html_link: string|null, ical_uid: string|null}
     */
    public function createEvent(
        BookingCalendarSelection $calendar,
        BookingPage $bookingPage,
        Booking $booking,
        bool $awaitingApproval = false,
    ): array;

    public function confirmEvent(
        GoogleCalendarConnection $connection,
        string $calendarId,
        string $eventId,
        Booking $booking,
    ): void;

    public function updateEventTime(
        GoogleCalendarConnection $connection,
        string $calendarId,
        string $eventId,
        Booking $booking,
        string $timezone,
    ): void;

    /**
     * @param  list<string>  $calendarIds
     * @return list<array{id: string, calendar_id: string, title: string, starts_at: CarbonImmutable, ends_at: CarbonImmutable, all_day: bool, link: string|null, type: string, busy: bool, location: string|null, description: string|null, organizer: string|null, attendees: list<array<string, mixed>>}>
     */
    public function eventsBetween(
        GoogleCalendarConnection $connection,
        array $calendarIds,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        string $timezone,
    ): array;

    /**
     * @return array{events: list<array<string, mixed>>, sync_token: string|null, expired: bool}
     */
    public function changedEvents(
        GoogleCalendarConnection $connection,
        string $calendarId,
        ?string $syncToken = null,
    ): array;

    /**
     * @return array{resource_id: string, expires_at: CarbonImmutable|null}
     */
    public function watchEvents(
        GoogleCalendarConnection $connection,
        string $calendarId,
        string $channelId,
        string $address,
        string $token,
        int $ttlSeconds,
    ): array;

    public function stopWatch(
        GoogleCalendarConnection $connection,
        string $channelId,
        string $resourceId,
    ): void;

    public function deleteEvent(
        GoogleCalendarConnection $connection,
        string $calendarId,
        string $eventId,
    ): void;

    public function revoke(GoogleCalendarConnection $connection): void;
}
