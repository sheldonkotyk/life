<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLoginLink extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $url, public string $code, public int $minutes)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your sign-in code for Life');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.magic-login',
            with: [
                'url' => $this->url,
                'code' => $this->code,
                'minutes' => $this->minutes,
            ],
        );
    }
}
