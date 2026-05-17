<?php

namespace App\Livewire;

use App\Models\Booking;
use App\Services\BookingConfirmationService;
use App\Services\PayMongoService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class ReservationSuccess extends Component
{
    public ?string $bookingRef = null;

    public ?int $pendingStartedAt = null;

    public bool $timedOut = false;

    public function mount(?string $bookingRef = null): void
    {
        $this->bookingRef = $bookingRef ?? request()->query('ref');

        if ($this->bookingRef) {
            $booking = Booking::query()->where('booking_ref', $this->bookingRef)->first();
            if ($booking && $this->isPaymongoPending($booking)) {
                $this->syncPendingPaymongoBooking($booking);

                if ($this->isPaymongoPending($booking->fresh())) {
                    $this->pendingStartedAt = time();
                }
            }
        }
    }

    public function isPaymongoPending(?Booking $booking): bool
    {
        if (! $booking) {
            return false;
        }

        return $booking->payment_method === 'paymongo'
            && $booking->payment_status === 'pending';
    }

    public function checkPaymentStatus(): void
    {
        if ($this->timedOut || ! $this->bookingRef) {
            return;
        }

        $booking = Booking::query()->where('booking_ref', $this->bookingRef)->first();

        if (! $booking) {
            return;
        }

        if ($booking->payment_status === 'paid') {
            return;
        }

        if ($this->isPaymongoPending($booking)) {
            $this->syncPendingPaymongoBooking($booking);
            $booking->refresh();

            if ($booking->payment_status === 'paid') {
                return;
            }

            if ($this->pendingStartedAt !== null && (time() - $this->pendingStartedAt) >= 120) {
                $this->timedOut = true;
            }
        }
    }

    private function syncPendingPaymongoBooking(Booking $booking): void
    {
        if (! $this->isPaymongoPending($booking) || empty($booking->paymongo_link_id)) {
            return;
        }

        $link = app(PayMongoService::class)->getPaymentLink((string) $booking->paymongo_link_id);
        if (! $link || ($link['status'] ?? null) !== 'paid') {
            return;
        }

        try {
            if (! empty($link['payment_id'])) {
                $booking->update([
                    'paymongo_payment_id' => $link['payment_id'],
                ]);
            }

            app(BookingConfirmationService::class)->confirm($booking->fresh());
        } catch (\Throwable $e) {
            Log::warning('PayMongo pending booking sync failed', [
                'booking_ref' => $booking->booking_ref,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function render()
    {
        $booking = $this->bookingRef
            ? Booking::query()->where('booking_ref', $this->bookingRef)->first()
            : null;

        $shouldPoll = $booking
            && $this->isPaymongoPending($booking)
            && ! $this->timedOut;

        return view('livewire.reservation-success', [
            'booking' => $booking,
            'bookingRef' => $this->bookingRef,
            'shouldPoll' => $shouldPoll,
        ]);
    }
}
