<?php

namespace App\Services;

use App\Jobs\SendSmsJob;
use App\Mail\BookingNoShowMail;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BookingNoShowNotifier
{
    public function send(Booking $booking): string
    {
        $email = trim((string) $booking->customer_email);
        $venueName = (string) config('app.venue_name', config('app.name'));

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            Mail::to($email, (string) $booking->customer_name)->send(new BookingNoShowMail(
                customerName: (string) $booking->customer_name,
                bookingRef: (string) $booking->booking_ref,
                venueName: $venueName,
            ));

            Log::info('Booking no-show email sent', [
                'booking_id' => $booking->id,
                'email_domain' => str_contains($email, '@') ? substr(strrchr($email, '@'), 1) : null,
            ]);

            return 'email';
        }

        $phone = trim((string) $booking->customer_phone);
        if ($phone === '') {
            Log::warning('Booking no-show notification skipped: no email or phone.', [
                'booking_id' => $booking->id,
            ]);

            return 'none';
        }

        dispatch(new SendSmsJob($phone, 'no_show', [
            'name' => $booking->customer_name,
            'venue' => $venueName,
            'ref' => $booking->booking_ref,
        ]));

        return 'sms';
    }
}
