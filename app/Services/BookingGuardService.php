<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\QueueEntry;

/**
 * BookingGuardService prevents duplicate bookings per phone number.
 * A customer can only have one active booking or queue entry at a time.
 */
class BookingGuardService
{
    /**
     * Check if a phone number already has an active entry.
     * Active entries include:
     * - Active bookings (not completed/cancelled)
     * - Pending bookings holding a slot (PayMongo checkout, paid, or manual QR awaiting verification)
     * - Waiting or notified queue entries
     *
     * @param string $phone The phone number to check.
     * @return bool True if an active entry exists.
     */
    public function hasActiveEntry(string $phone): bool
    {
        $bookingBlocks = Booking::where('customer_phone', $phone)
            ->where(function ($q) {
                $q->where('status', 'active')
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'pending')
                            ->whereIn('payment_status', ['pending', 'paid', 'pending_verification']);
                    });
            })
            ->exists();

        $activeQueue = QueueEntry::where('customer_phone', $phone)
            ->whereIn('status', ['waiting', 'notified'])
            ->exists();

        return $bookingBlocks || $activeQueue;
    }

    /**
     * True if this phone already has a future booking that is still pending or active and not failed/cancelled.
     */
    public function hasActivePendingReservation(string $phone, bool $lockForUpdate = false): bool
    {
        $query = Booking::query()
            ->where('customer_phone', $phone)
            ->whereIn('status', ['pending', 'active'])
            ->whereNotIn('payment_status', ['failed', 'cancelled'])
            ->where('booked_at', '>', now());

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->exists();
    }
}
