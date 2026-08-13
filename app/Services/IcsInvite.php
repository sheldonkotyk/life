<?php

namespace App\Services;

use App\Models\Booking;

/**
 * Minimal iCalendar payloads so a guest's calendar can hold a requested time
 * before it becomes a real Google invitation.
 */
class IcsInvite
{
    public static function hold(Booking $booking, string $organiserEmail, int $sequence = 0): string
    {
        return self::build($booking, $organiserEmail, method: 'REQUEST', status: 'TENTATIVE', sequence: $sequence);
    }

    public static function release(Booking $booking, string $organiserEmail): string
    {
        return self::build($booking, $organiserEmail, method: 'CANCEL', status: 'CANCELLED', sequence: 2);
    }

    private static function build(
        Booking $booking,
        string $organiserEmail,
        string $method,
        string $status,
        int $sequence,
    ): string {
        $page = $booking->bookingPage;
        $stamp = now()->utc()->format('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Life//Bookings//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:'.$method,
            'BEGIN:VEVENT',
            'UID:'.($booking->google_ical_uid ?: 'lifebooking'.$booking->id.'@life.local'),
            'SEQUENCE:'.$sequence,
            'DTSTAMP:'.$stamp,
            'DTSTART:'.$booking->starts_at->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$booking->ends_at->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.self::escape($booking->summary()),
            'DESCRIPTION:'.self::escape(self::description($booking)),
            'STATUS:'.$status,
            'ORGANIZER;CN='.self::escape($page->user->name).':mailto:'.$organiserEmail,
            'ATTENDEE;CN='.self::escape($booking->guest_name)
                .';ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:mailto:'.$booking->guest_email,
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", $lines)."\r\n";
    }

    private static function description(Booking $booking): string
    {
        $links = 'Change the time: '.$booking->rescheduleUrl()
            ."\nCancel: ".$booking->cancelUrl();

        if ($booking->isAwaitingApproval()) {
            return 'Holding this time while '.$booking->bookingPage->user->name." confirms.\n\n".$links;
        }

        return "Booked through Life.\n\n".$links;
    }

    private static function escape(string $value): string
    {
        return str_replace(
            ['\\', "\n", ',', ';'],
            ['\\\\', '\\n', '\\,', '\;'],
            $value,
        );
    }
}
