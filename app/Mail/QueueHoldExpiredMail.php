<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QueueHoldExpiredMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $customerName,
        public string $venueName,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your waitlist hold expired at Cafe Gervacios',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.queue-hold-expired',
            text: 'emails.queue-hold-expired-text',
        );
    }
}
