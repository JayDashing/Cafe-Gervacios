<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingNoShowMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $customerName,
        public string $bookingRef,
        public string $venueName,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reservation marked no-show at Cafe Gervacios',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-no-show',
            text: 'emails.booking-no-show-text',
        );
    }
}
