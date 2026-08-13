<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the owner a guest turned down a meeting they had accepted, so the time
 * is theirs again.
 */
class BookingGuestDeclined extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->booking->guest_name.' declined '.$this->booking->starts_at
                ->setTimezone($this->booking->bookingPage->timezone)
                ->format('D, M j · g:i A'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-guest-declined',
            with: [
                'booking' => $this->booking,
                'bookingPage' => $this->booking->bookingPage,
            ],
        );
    }
}
