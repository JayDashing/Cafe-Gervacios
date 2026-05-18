<?php

namespace App\Services;

use App\Jobs\NotifyNextAfterTableReleaseJob;
use App\Jobs\SendSmsJob;
use App\Models\Booking;
use App\Models\Seat;
use App\Models\Setting;
use App\Models\Table;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TableService
{
    public function book(
        int $tableId,
        int $partySize,
        string $name,
        string $phone,
        string $priorityType,
        string $source = 'website',
        ?string $deviceType = null
    ): Booking {
        $table = Table::findOrFail($tableId);

        if (!$table->occupy($partySize)) {
            throw new \Exception("Table {$tableId} is no longer available.");
        }

        $booking = Booking::create([
            'booking_ref' => $this->generateRef(),
            'table_id' => $tableId,
            'customer_name' => $name,
            'customer_phone' => $phone,
            'party_size' => $partySize,
            'priority_type' => $priorityType,
            'source' => $source,
            'device_type' => $deviceType,
        ]);

        $booking->refresh();

        dispatch(new SendSmsJob($phone, 'booking_confirmed', [
            'name' => $name,
            'booking_ref' => $booking->booking_ref,
            'venue' => config('app.venue_name', config('app.name')),
            'customer_email' => $booking->customer_email,
            'booked_at' => $booking->booked_at?->toIso8601String(),
            'party_size' => $partySize,
        ]));

        Cache::forget('tables.venue.1');
        app(QueueService::class)->refreshEstimatedWaits();

        return $booking;
    }

    public function release(int $tableId): void
    {
        $table = Table::findOrFail($tableId);
        $table->release();

        Seat::where('table_id', $table->id)->update(['status' => $this->mapTableStatusToSeatStatus('cleaning')]);

        Cache::forget('tables.venue.1');
        app(QueueService::class)->refreshEstimatedWaits();

        $this->dispatchNotifyNextAfterTableReleaseDelayed();
    }

    public function override(int $tableId, string $status): void
    {
        $payload = ['status' => $status];

        if ($status === 'available') {
            $payload['cleaning_started_at'] = null;
            $payload['booking_id'] = null;
        } elseif ($status === 'cleaning') {
            $payload['occupied_at'] = null;
            $payload['occupied_party'] = null;
            $t = Table::findOrFail($tableId);
            $payload['cleaning_started_at'] = now()->copy()->addMicroseconds(min((int) $t->id, 999_999));
        }

        $table = Table::findOrFail($tableId);
        $table->update($payload);

        Seat::where('table_id', $table->id)->update(['status' => $this->mapTableStatusToSeatStatus($status)]);

        Cache::forget('tables.venue.1');

        if ($status === 'available') {
            app(QueueService::class)->notifyNextAfterTableRelease();
        } else {
            app(QueueService::class)->refreshEstimatedWaits();
        }
    }

    public function releaseExpired(): int
    {
        $duration = config('operations.occupancy_duration_minutes', 90);
        $cutoff = now()->subMinutes($duration);

        $expired = Table::where('status', 'occupied')
            ->where('occupied_at', '<', $cutoff)
            ->get();

        foreach ($expired as $table) {
            $table->release();
        }

        if ($expired->count() > 0) {
            Cache::forget('tables.venue.1');
            app(QueueService::class)->refreshEstimatedWaits();
            $this->dispatchNotifyNextAfterTableReleaseDelayed();
        }

        return $expired->count();
    }

    public function markReadyAfterCleaning(int $tableId): void
    {
        $this->override($tableId, 'available');
    }

    private function mapTableStatusToSeatStatus(string $tableStatus): string
    {
        return match ($tableStatus) {
            'available' => 'free',
            'reserved' => 'reserved',
            'occupied' => 'occupied',
            'cleaning' => 'cleaning',
            'unavailable' => 'free',
            default => 'free',
        };
    }

    /**
     * Wait `table_cleaning_minutes` setting (default 10) before notifying the next waitlist party,
     * so "available" tables are not offered until cleaning time has passed.
     */
    private function dispatchNotifyNextAfterTableReleaseDelayed(): void
    {
        $mins = max(0, (int) Setting::get('table_cleaning_minutes', (string) config('automation.table_cleaning_minutes', 10)));

        NotifyNextAfterTableReleaseJob::dispatch()->delay(now()->addMinutes($mins));
    }

    private function generateRef(): string
    {
        do {
            $ref = 'GRV-' . strtoupper(Str::random(7));
        } while (Booking::where('booking_ref', $ref)->exists());

        return $ref;
    }
}
