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
     * @return array{id: string, html_link: string|null}
     */
    public function createEvent(
        BookingCalendarSelection $calendar,
        BookingPage $bookingPage,
        Booking $booking,
    ): array;

    public function deleteEvent(
        GoogleCalendarConnection $connection,
        string $calendarId,
        string $eventId,
    ): void;

    public function revoke(GoogleCalendarConnection $connection): void;
}
