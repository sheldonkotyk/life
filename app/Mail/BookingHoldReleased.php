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
 * Sent when a held request is declined, so the guest's calendar drops the hold.
 */
class BookingHoldReleased extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking, public string $organiserEmail) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'That time is no longer available');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-hold-released',
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
                fn (): string => IcsInvite::release($this->booking, $this->organiserEmail),
                'cancel.ics',
            )->withMime('text/calendar; method=CANCEL; charset=UTF-8'),
        ];
    }
}
