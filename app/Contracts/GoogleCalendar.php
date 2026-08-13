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

    public function deleteEvent(
        GoogleCalendarConnection $connection,
        string $calendarId,
        string $eventId,
    ): void;

    public function revoke(GoogleCalendarConnection $connection): void;
}
