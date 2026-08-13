<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the calendar owner someone booked, or asked to. A request carries the
 * same accept and decline links as the tentative calendar entry.
 */
class BookingReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        $when = $this->booking->starts_at
            ->setTimezone($this->booking->bookingPage->timezone)
            ->format('D, M j · g:i A');

        return new Envelope(
            subject: $this->booking->isAwaitingApproval()
                ? $this->booking->guest_name.' asked for '.$when
                : $this->booking->guest_name.' booked '.$when,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-received',
            with: [
                'booking' => $this->booking,
                'bookingPage' => $this->booking->bookingPage,
            ],
        );
    }
}
