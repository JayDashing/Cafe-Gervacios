<?php

namespace App\Services;

use App\Jobs\SendSmsJob;
use App\Models\Booking;
use App\Models\Table;
use Illuminate\Support\Facades\DB;

class BookingConfirmationService
{
    public function __construct(
        private BookingTableService $bookingTableService
    ) {}

    public function confirm(Booking $booking): ?Table
    {
        $sendConfirmation = false;

        $booking = DB::transaction(function () use ($booking, &$sendConfirmation) {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->payment_status !== 'paid') {
                $locked->update([
                    'payment_status' => 'paid',
                    'status' => 'active',
                    'paid_at' => now(),
                ]);

                $sendConfirmation = true;
            } elseif ($locked->status !== 'active') {
                $locked->update(['status' => 'active']);
            }

            return $locked->fresh();
        });

        $table = $this->bookingTableService->assignTable($booking);

        if ($sendConfirmation) {
            SendSmsJob::dispatch(
                $booking->customer_phone,
                'booking_confirmed',
                [
                    'name' => $booking->customer_name,
                    'venue' => config('app.name', 'Cafe Gervacios'),
                    'booking_ref' => $booking->booking_ref,
                    'customer_email' => $booking->customer_email,
                    'booked_at' => $booking->booked_at?->toIso8601String(),
                    'party_size' => $booking->party_size,
                ]
            );
        }

        return $table;
    }

    public function reject(Booking $booking, string $reason = ''): void
    {
        $sendFailureNotice = false;

        $booking = DB::transaction(function () use ($booking, &$sendFailureNotice) {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->payment_status !== 'failed' && $locked->status !== 'cancelled') {
                $locked->update([
                    'payment_status' => 'failed',
                    'status' => 'cancelled',
                ]);

                $sendFailureNotice = true;
            }

            return $locked->fresh();
        });

        if (! $sendFailureNotice) {
            return;
        }

        $phone = trim((string) ($booking->customer_phone ?? ''));
        if ($phone === '') {
            return;
        }

        $siteUrl = rtrim((string) config('app.url'), '/');
        if ($siteUrl === '') {
            $siteUrl = url('/');
        }

        $payload = [
            'site_url' => $siteUrl,
        ];
        if ($reason !== '') {
            $payload['reason'] = $reason;
        }

        SendSmsJob::dispatch($phone, 'payment_rejected', $payload);
    }
}
