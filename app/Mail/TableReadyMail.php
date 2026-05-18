<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TableReadyMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $customerName,
        public string $venueName,
        public ?string $tableLabel,
        public int $holdMinutes,
        public string $confirmationCode,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your table is ready at Cafe Gervacios',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.table-ready',
            text: 'emails.table-ready-text',
        );
    }
}
