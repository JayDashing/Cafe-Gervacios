<?php

namespace App\Livewire\Admin;

use App\Models\Booking;
use App\Models\QueueEntry;
use App\Models\Table;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class FocusModeBoard extends Component
{
    public function goToFloorMapForSeat(int $entryId): void
    {
        $entry = QueueEntry::query()->findOrFail($entryId);

        if (! in_array($entry->status, ['waiting', 'notified'], true)) {
            $this->dispatch('notify', type: 'error', message: 'This queue entry is no longer active.');

            return;
        }

        $this->redirect(route('admin.tables', ['focus_queue' => $entry->id]), navigate: false);
    }

    public function checkIn(int $bookingId): void
    {
        $user = auth()->user();
        if (! $user || ! in_array($user->role, ['admin', 'staff', 'superadmin'], true)) {
            abort(403);
        }

        DB::transaction(function () use ($bookingId) {
            $booking = Booking::query()->lockForUpdate()->findOrFail($bookingId);

            if (
                $booking->status !== 'active'
                || $booking->payment_status !== 'paid'
                || $booking->checked_in_at !== null
                || ! $booking->booked_at?->isToday()
            ) {
                return;
            }

            if ($booking->table_id !== null) {
                $table = Table::query()->lockForUpdate()->find($booking->table_id);
                if ($table) {
                    if ($table->status === 'reserved') {
                        if (! $table->occupyFromReserved((int) $booking->party_size)) {
                            throw new \RuntimeException('Could not occupy reserved table.');
                        }
                    } elseif ($table->status === 'available') {
                        if (! $table->occupy((int) $booking->party_size)) {
                            throw new \RuntimeException('Could not occupy table.');
                        }
                    }
                }
            }

            $booking->update(['checked_in_at' => now()]);
        });

        Cache::forget('tables.venue.1');
        $this->dispatch('tables-refresh');
        $this->dispatch('table-updated');
        $this->dispatch('reservation-updated');
        $this->dispatch('queue-updated');
        $this->dispatch('eta-recalculated');
        $this->dispatch('notify', type: 'success', message: 'Reservation checked in.');
    }

    public function render()
    {
        $queueEntries = QueueEntry::query()
            ->whereIn('status', ['waiting', 'notified'])
            ->orderBy('joined_at')
            ->get();

        $reservations = Booking::query()
            ->with('table')
            ->where('status', 'active')
            ->where('payment_status', 'paid')
            ->whereDate('booked_at', today())
            ->whereNull('checked_in_at')
            ->orderBy('booked_at')
            ->get();

        return view('livewire.admin.focus-mode-board', [
            'queueEntries' => $queueEntries,
            'reservations' => $reservations,
        ]);
    }
}
