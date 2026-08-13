<?php

namespace App\Mail;

use App\Models\Booking;
use App\Services\IcsInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the guest while a request waits, so the time is held on their
 * calendar too rather than only on the owner's.
 */
class BookingHoldPlaced extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking, public string $organiserEmail) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Holding '.$this->booking->starts_at
                ->setTimezone($this->booking->guest_timezone)
                ->format('D, M j · g:i A').' for you',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-hold',
            with: [
                'booking' => $this->booking,
                'bookingPage' => $this->booking->bookingPage,
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn (): string => IcsInvite::hold($this->booking, $this->organiserEmail),
                'invite.ics',
            )->withMime('text/calendar; method=REQUEST; charset=UTF-8'),
        ];
    }
}
