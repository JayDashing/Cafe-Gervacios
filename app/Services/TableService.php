<?php

namespace App\Services;

use App\Jobs\NotifyNextAfterTableReleaseJob;
use App\Jobs\SendSmsJob;
use App\Models\Booking;
use App\Models\Seat;
use App\Models\Setting;
use App\Models\Table;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

    public function checkIn(int $tableId): void
    {
        DB::transaction(function () use ($tableId) {
            $table = Table::query()
                ->with('booking')
                ->lockForUpdate()
                ->findOrFail($tableId);

            $this->assertTransition($table, [Table::STATUS_RESERVED], 'check in');

            if ($table->booking_id === null || $table->booking === null) {
                throw new \InvalidArgumentException('Only reserved tables with an online booking can be checked in.');
            }

            $partySize = max(1, (int) ($table->booking->party_size ?? $table->capacity));
            $table->update([
                'status' => Table::STATUS_OCCUPIED,
                'occupied_at' => now(),
                'occupied_party' => $partySize,
                'cleaning_started_at' => null,
            ]);

            $table->booking->update(['checked_in_at' => now()]);
            $this->syncSeats($table->id, Table::STATUS_OCCUPIED);
        });

        $this->afterTableChanged(Table::STATUS_OCCUPIED);
    }

    public function noShow(int $tableId): void
    {
        DB::transaction(function () use ($tableId) {
            $table = Table::query()
                ->with('booking')
                ->lockForUpdate()
                ->findOrFail($tableId);

            $this->assertTransition($table, [Table::STATUS_RESERVED], 'mark no-show');

            if ($table->booking_id === null || $table->booking === null) {
                throw new \InvalidArgumentException('Only reserved tables with an online booking can be marked no-show.');
            }

            $table->booking->update([
                'status' => 'cancelled',
                'no_show_at' => now(),
                'table_id' => null,
            ]);

            $table->update([
                'status' => Table::STATUS_AVAILABLE,
                'booking_id' => null,
                'occupied_at' => null,
                'occupied_party' => null,
                'cleaning_started_at' => null,
            ]);

            $this->syncSeats($table->id, Table::STATUS_AVAILABLE);
        });

        $this->afterTableChanged(Table::STATUS_AVAILABLE);
    }

    public function seatWalkIn(int $tableId): void
    {
        DB::transaction(function () use ($tableId) {
            $table = Table::query()
                ->lockForUpdate()
                ->findOrFail($tableId);

            if (! in_array($table->status, [Table::STATUS_AVAILABLE, Table::STATUS_RESERVED], true)) {
                throw new \InvalidArgumentException('Walk-ins can only be seated at free tables.');
            }

            if ($table->booking_id !== null) {
                throw new \InvalidArgumentException('This table has an online booking and cannot seat a walk-in.');
            }

            $table->update([
                'status' => Table::STATUS_OCCUPIED,
                'occupied_at' => now(),
                'occupied_party' => max(1, (int) $table->capacity),
                'cleaning_started_at' => null,
            ]);

            $this->syncSeats($table->id, Table::STATUS_OCCUPIED);
        });

        $this->afterTableChanged(Table::STATUS_OCCUPIED);
    }

    public function seatFromQueue(int $tableId, int $entryId): void
    {
        app(QueueService::class)->seat($entryId, $tableId);
    }

    public function sendToCleaning(int $tableId): void
    {
        DB::transaction(function () use ($tableId) {
            $table = Table::query()
                ->lockForUpdate()
                ->findOrFail($tableId);

            $this->assertTransition($table, [Table::STATUS_OCCUPIED], 'send to cleaning');

            $table->update([
                'status' => Table::STATUS_CLEANING,
                'occupied_at' => null,
                'occupied_party' => null,
                'booking_id' => null,
                'cleaning_started_at' => $this->cleaningStartedAt($table),
            ]);

            $this->syncSeats($table->id, Table::STATUS_CLEANING);
        });

        $this->afterTableChanged(Table::STATUS_CLEANING, delayedQueueNotice: true);
    }

    public function markFree(int $tableId): void
    {
        DB::transaction(function () use ($tableId) {
            $table = Table::query()
                ->lockForUpdate()
                ->findOrFail($tableId);

            $this->assertTransition($table, [Table::STATUS_OCCUPIED, Table::STATUS_CLEANING], 'mark free');

            $table->update([
                'status' => Table::STATUS_AVAILABLE,
                'occupied_at' => null,
                'occupied_party' => null,
                'booking_id' => null,
                'cleaning_started_at' => null,
            ]);

            $this->syncSeats($table->id, Table::STATUS_AVAILABLE);
        });

        $this->afterTableChanged(Table::STATUS_AVAILABLE);
    }

    public function releaseTable(int $tableId): void
    {
        DB::transaction(function () use ($tableId) {
            $table = Table::query()
                ->lockForUpdate()
                ->findOrFail($tableId);

            $this->assertTransition($table, [Table::STATUS_RESERVED], 'release table');

            $table->update([
                'status' => Table::STATUS_AVAILABLE,
                'occupied_at' => null,
                'occupied_party' => null,
                'cleaning_started_at' => null,
            ]);

            $this->syncSeats($table->id, Table::STATUS_AVAILABLE);
        });

        $this->afterTableChanged(Table::STATUS_AVAILABLE);
    }

    public function release(int $tableId): void
    {
        $this->sendToCleaning($tableId);
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
        $this->markFree($tableId);
    }

    private function mapTableStatusToSeatStatus(string $tableStatus): string
    {
        return match ($tableStatus) {
            Table::STATUS_AVAILABLE => Seat::STATUS_FREE,
            Table::STATUS_RESERVED => Seat::STATUS_RESERVED,
            Table::STATUS_OCCUPIED => Seat::STATUS_OCCUPIED,
            Table::STATUS_CLEANING => Seat::STATUS_CLEANING,
            Table::STATUS_UNAVAILABLE => Seat::STATUS_FREE,
            default => throw new \InvalidArgumentException('Invalid table status.'),
        };
    }

    private function syncSeats(int $tableId, string $tableStatus): void
    {
        Seat::where('table_id', $tableId)->update(['status' => $this->mapTableStatusToSeatStatus($tableStatus)]);
    }

    private function assertTransition(Table $table, array $allowedStatuses, string $action): void
    {
        if (! in_array($table->status, $allowedStatuses, true)) {
            throw new \InvalidArgumentException("Cannot {$action} from {$table->status} status.");
        }
    }

    private function cleaningStartedAt(Table $table): \Carbon\Carbon
    {
        return now()->copy()->addMicroseconds(min((int) $table->id, 999_999));
    }

    private function afterTableChanged(string $status, bool $delayedQueueNotice = false): void
    {
        Cache::forget('tables.venue.1');

        if ($delayedQueueNotice) {
            app(QueueService::class)->refreshEstimatedWaits();
            $this->dispatchNotifyNextAfterTableReleaseDelayed();

            return;
        }

        if ($status === Table::STATUS_AVAILABLE) {
            app(QueueService::class)->notifyNextAfterTableRelease();

            return;
        }

        app(QueueService::class)->refreshEstimatedWaits();
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
