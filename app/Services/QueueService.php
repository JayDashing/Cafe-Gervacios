<?php

namespace App\Services;

use App\Models\QueueEntry;
use App\Models\Setting;
use App\Models\Table;
use App\Jobs\SendSmsJob;
use App\Models\AutomationLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QueueService
{
    /**
     * Why the waitlist might not auto-advance when a table is Free (for staff UI).
     *
     * @return list<string>
     */
    public function waitlistStaffHints(): array
    {
        $hints = [];

        if (!AutomationSettings::bool('automation_notify_queue_on_release', true)) {
            $hints[] = 'Auto table-ready SMS is turned off. Use the blue SMS button for each guest when a table suits them.';
        }

        if (!AutomationSettings::effectivePeakForQueueNotify()) {
            $hints[] = 'Outside the busy-hours window — auto table-ready SMS is paused. Use SMS to call the next guest, or tap Override.';
        }

        if (Setting::get('sms_enabled', '1') !== '1') {
            $hints[] = 'SMS is disabled in settings — texts will not send until SMS is turned on.';
        }

        $waiting = QueueEntry::waiting()->sorted()->get();
        $tables = Table::query()
            ->where('status', 'available')
            ->orderBy('capacity')
            ->orderBy('id')
            ->get();

        if ($waiting->isEmpty()) {
            return $hints;
        }

        if ($tables->isEmpty()) {
            $hints[] = 'Guests are waiting, but no table is Free (ready) yet. Finish cleaning and tap Done cleaning / Free on a table.';

            return $hints;
        }

        if (!$this->anyWaitingGuestFitsAnyTable($waiting, $tables)) {
            $hints[] = 'No Free table fits the next guest(s) by party size (and accessible-table rule if enabled for PWD in settings). Free a suitable table or use SMS when you seat them manually.';
        }

        return $hints;
    }

    /**
     * @param  Collection<int, QueueEntry>  $waiting
     * @param  Collection<int, Table>  $tables
     */
    private function anyWaitingGuestFitsAnyTable(Collection $waiting, Collection $tables): bool
    {
        foreach ($waiting as $entry) {
            if ($tables->first(fn(Table $t) => $this->tableFitsEntry($entry, $t))) {
                return true;
            }
        }

        return false;
    }

    public function tableFitsEntry(QueueEntry $entry, Table $table): bool
    {
        return $entry->accommodates($table);
    }

    /**
     * Add a customer to the unified waitlist (e.g. staff walk-in registration).
     */
    public function join(
        string $name,
        ?string $phone,
        int $partySize,
        string $priorityType,
        string $source = 'website',
        ?string $deviceType = null
    ): QueueEntry {
        $phone = trim((string) $phone);

        $priority = app(PriorityService::class);
        $score = $priority->getScore($priorityType);

        $entry = DB::transaction(function () use ($name, $phone, $partySize, $priorityType, $priority, $score, $source, $deviceType) {
            $max = QueueEntry::whereDate('joined_at', today())
                ->lockForUpdate()
                ->max('queue_display_number');

            $displayNumber = (int) ($max ?? 0) + 1;

            return QueueEntry::create([
                'customer_name' => $name,
                'customer_phone' => $phone,
                'party_size' => $partySize,
                'priority_type' => $priorityType,
                'priority_score' => $score,
                'needs_accessible' => $priority->requiresAccessibleTable($priorityType),
                'estimated_wait' => $this->estimateWait($partySize),
                'last_estimated_wait' => $this->estimateWait($partySize),
                'joined_at' => now(),
                'source' => $source,
                'device_type' => $deviceType,
                'queue_display_number' => $displayNumber,
            ]);
        });

        if ($phone !== '') {
            dispatch(new SendSmsJob($phone, 'queue_joined', [
                'name' => $name,
                'position' => (string) $this->positionFor($entry),
                'wait' => $entry->estimated_wait,
                'queue_no' => (string) $entry->queue_display_number,
                'venue' => config('app.venue_name', config('app.name')),
                'is_priority' => $score === 100,
            ]));
        }

        return $entry;
    }

    public function seat(int $entryId, int $tableId): void
    {
        $entry = QueueEntry::findOrFail($entryId);
        $table = Table::findOrFail($tableId);

        if (!in_array($entry->status, ['waiting', 'notified'], true)) {
            throw new \InvalidArgumentException('This waitlist entry cannot be seated (wrong status).');
        }

        if (!$entry->accommodates($table)) {
            throw new \InvalidArgumentException('That table does not fit this party (size or accessibility).');
        }

        DB::transaction(function () use ($entryId, $tableId) {
            $entry = QueueEntry::query()->whereKey($entryId)->lockForUpdate()->firstOrFail();
            $table = Table::query()->whereKey($tableId)->lockForUpdate()->firstOrFail();

            if (!in_array($entry->status, ['waiting', 'notified'], true)) {
                throw new \InvalidArgumentException('This waitlist entry cannot be seated (wrong status).');
            }

            if (!$entry->accommodates($table)) {
                throw new \InvalidArgumentException('That table does not fit this party (size or accessibility).');
            }

            $reservedId = $entry->reserved_table_id;

            if ($reservedId !== null && (int) $reservedId !== (int) $table->id) {
                Table::query()
                    ->where('id', $reservedId)
                    ->where('status', 'reserved')
                    ->update(['status' => 'available', 'booking_id' => null]);
            }

            $sameReserved = $reservedId !== null && (int) $reservedId === (int) $table->id && $table->status === 'reserved';

            if ($sameReserved) {
                $ok = $table->occupyFromReserved((int) $entry->party_size);
            } else {
                $ok = $table->occupy((int) $entry->party_size);
            }

            if (!$ok) {
                throw new \RuntimeException('Table could not be seated (must be Free or their held table).');
            }

            $entry->update([
                'status' => 'seated',
                'seated_at' => now(),
                'reserved_table_id' => null,
            ]);
        });

        $entry->refresh();
        $table->refresh();

        if ($entry->isPriority()) {
            app(PriorityService::class)->logSeatEvent($entry, $table);
        }

        Cache::forget('tables.venue.1');
    }

    public function cancel(int $entryId): void
    {
        $entry = QueueEntry::findOrFail($entryId);

        DB::transaction(function () use ($entry) {
            if ($entry->reserved_table_id !== null) {
                Table::query()
                    ->where('id', $entry->reserved_table_id)
                    ->where('status', 'reserved')
                    ->update(['status' => 'available', 'booking_id' => null]);
            }

            $entry->update([
                'status' => 'cancelled',
                'reserved_table_id' => null,
            ]);
        });
    }

    /**
     * When a notified guest’s hold expires: free the held table, cancel the entry, SMS, notify next.
     */
    public function finalizeExpiredNotifiedHold(QueueEntry $entry): void
    {
        $phone = $entry->customer_phone;
        $name = $entry->customer_name;
        $entryId = $entry->id;

        $processed = false;

        DB::transaction(function () use ($entryId, &$processed) {
            $row = QueueEntry::query()->whereKey($entryId)->lockForUpdate()->first();
            if (
                $row === null
                || $row->status !== 'notified'
                || $row->hold_expires_at === null
                || $row->hold_expires_at->gte(now())
            ) {
                return;
            }

            if ($row->reserved_table_id !== null) {
                Table::query()
                    ->where('id', $row->reserved_table_id)
                    ->where('status', 'reserved')
                    ->update(['status' => 'available', 'booking_id' => null]);
            }

            QueueEntry::query()->whereKey($row->id)->update([
                'status' => 'cancelled',
                'skipped_at' => now(),
                'reserved_table_id' => null,
            ]);

            $processed = true;
        });

        if (!$processed) {
            return;
        }

        dispatch(new SendSmsJob($phone, 'queue_skipped', [
            'name' => $name,
            'venue' => config('app.venue_name', config('app.name')),
        ]));

        AutomationLog::record('queue_holds', 'Skipped hold expired', ['entry_id' => $entryId]);
        $this->notifyNextAfterTableRelease();
    }

    /**
     * Notify the next customer who fits in at least one available table.
     * Waitlist is sorted with priority (PWD / senior / pregnant) ahead of regular, then by join time.
     * The first person in that order for whom a free table matches capacity (and optional PWD ♿ rule) is
     * texted and gets the first matching table in capacity order — priority controls who is called first, not
     * which table type they receive.
     */
    public function notifyNextMatchingTables(): void
    {
        if (!AutomationSettings::bool('automation_notify_queue_on_release', true)) {
            return;
        }

        if (!AutomationSettings::effectivePeakForQueueNotify()) {
            return;
        }

        $tables = Table::query()
            ->where('status', 'available')
            ->orderBy('capacity')
            ->orderBy('id')
            ->get();

        if ($tables->isEmpty()) {
            return;
        }

        $waiting = QueueEntry::waiting()->sorted()->get();

        $next = null;
        $assignedTable = null;

        foreach ($waiting as $entry) {
            $table = $tables->first(fn(Table $t) => $this->tableFitsEntry($entry, $t));

            if ($table) {
                $next = $entry;
                $assignedTable = $table;

                break;
            }
        }

        if (!$next || !$assignedTable) {
            return;
        }

        $holdMinutes = AutomationSettings::int('automation_queue_hold_minutes', (int) config('automation.queue_hold_minutes', 1));

        $venueName = config('app.venue_name', config('app.name'));

        $nextId = $next->id;
        $tableId = $assignedTable->id;

        $reservedOk = false;

        DB::transaction(function () use ($nextId, $tableId, $holdMinutes, &$reservedOk) {
            $entry = QueueEntry::query()->whereKey($nextId)->lockForUpdate()->first();
            $table = Table::query()->whereKey($tableId)->lockForUpdate()->first();

            if ($entry === null || $entry->status !== 'waiting' || $table === null || $table->status !== 'available') {
                return;
            }

            if (!$table->reserve()) {
                return;
            }

            $code = strtoupper(Str::random(6));

            $entry->update([
                'status' => 'notified',
                'notified_at' => now(),
                'hold_expires_at' => now()->addMinutes($holdMinutes),
                'reserved_table_id' => $table->id,
                'hold_confirmation_code' => $code,
            ]);

            $reservedOk = true;
        });

        if (!$reservedOk) {
            return;
        }

        $next->refresh();
        $assignedTable->refresh();

        if ($next->status !== 'notified' || (int) $next->reserved_table_id !== (int) $assignedTable->id) {
            return;
        }

        dispatch(new SendSmsJob($next->customer_phone, 'table_ready', [
            'name' => $next->customer_name,
            'venue' => $venueName . ' — Table ' . $assignedTable->label,
            'is_priority' => $next->isPriority(),
            'minutes' => (string) $holdMinutes,
            'code' => $next->hold_confirmation_code ?? '',
        ]));

        Cache::forget('tables.venue.1');
    }

    /**
     * @deprecated Use notifyNextMatchingTables()
     */
    public function notifyNext(int $tableCapacity): void
    {
        $this->notifyNextMatchingTables();
    }

    public function notifyNextAfterTableRelease(): void
    {
        $this->notifyNextMatchingTables();
    }

    public function notifyEntryManually(int $entryId): QueueEntry
    {
        $holdMinutes = AutomationSettings::int('automation_queue_hold_minutes', (int) config('automation.queue_hold_minutes', 1));
        $venueName = config('app.venue_name', config('app.name'));

        $reservedTableId = null;

        DB::transaction(function () use ($entryId, $holdMinutes, &$reservedTableId) {
            $entry = QueueEntry::query()->whereKey($entryId)->lockForUpdate()->firstOrFail();

            if (! in_array($entry->status, ['waiting', 'notified'], true)) {
                throw new \InvalidArgumentException('This waitlist entry cannot be notified.');
            }

            if (trim((string) $entry->customer_phone) === '') {
                throw new \InvalidArgumentException('This guest has no phone number for SMS.');
            }

            $table = null;

            if ($entry->reserved_table_id !== null) {
                $held = Table::query()->whereKey($entry->reserved_table_id)->lockForUpdate()->first();
                if ($held !== null && $this->tableFitsEntry($entry, $held)) {
                    if ($held->status === 'reserved') {
                        $table = $held;
                    } elseif ($held->status === 'available' && $held->reserve()) {
                        $table = $held->refresh();
                    }
                }
            }

            if ($table === null) {
                $availableTables = Table::query()
                    ->where('status', 'available')
                    ->where('capacity', '>=', $entry->party_size)
                    ->orderBy('capacity')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $table = $this->sortTablesForParty($availableTables, $entry)->first();

                if ($table === null) {
                    throw new \InvalidArgumentException('No free table fits this party.');
                }

                if (! $table->reserve()) {
                    throw new \RuntimeException('Table could not be held. Please try again.');
                }

                $table->refresh();
            }

            $attrs = [
                'status' => 'notified',
                'notified_at' => now(),
                'hold_expires_at' => now()->addMinutes($holdMinutes),
                'reserved_table_id' => $table->id,
            ];

            if ($entry->hold_confirmation_code === null || $entry->hold_confirmation_code === '') {
                $attrs['hold_confirmation_code'] = strtoupper(Str::random(6));
            }

            $entry->update($attrs);
            $reservedTableId = (int) $table->id;
        });

        $entry = QueueEntry::query()->findOrFail($entryId);
        $table = $reservedTableId !== null ? Table::query()->find($reservedTableId) : null;

        dispatch(new SendSmsJob($entry->customer_phone, 'table_ready', [
            'name' => $entry->customer_name,
            'venue' => $venueName.($table ? ' - Table '.$table->label : ''),
            'is_priority' => $entry->isPriority(),
            'minutes' => (string) $holdMinutes,
            'code' => $entry->hold_confirmation_code ?? '',
        ]));

        Cache::forget('tables.venue.1');

        return $entry;
    }

    public function estimateWait(int $partySize): int
    {
        $duration = max(1, (int) config('operations.occupancy_duration_minutes', 90));

        $occupied = Table::where('status', 'occupied')
            ->where('capacity', '>=', $partySize)
            ->get();

        if ($occupied->isEmpty()) {
            return 0;
        }

        $avgRemaining = $occupied->map(function ($t) use ($duration) {
            $occupiedAt = $t->occupied_at;
            if ($occupiedAt === null) {
                return $duration;
            }

            if ($occupiedAt->isFuture()) {
                return $duration;
            }

            // Elapsed since seat: use start->diffInMinutes(end, true) so the sign is always correct.
            // (now()->diffInMinutes($occupied_at) defaults to absolute=false and can be negative when
            // occupied_at is in the future, which made $duration - $elapsed explode past column limits.)
            $elapsed = max(0, (int) round($occupiedAt->diffInMinutes(now(), true)));

            return max(0, $duration - $elapsed);
        })->average();

        return min(65535, max(0, (int) round($avgRemaining)));
    }

    public function positionFor(QueueEntry $entry): int
    {
        return QueueEntry::waiting()
            ->where(function ($q) use ($entry) {
                $q->where('priority_score', '>', $entry->priority_score)
                    ->orWhere(function ($q2) use ($entry) {
                        $q2->where('priority_score', $entry->priority_score)
                            ->where('joined_at', '<', $entry->joined_at);
                    });
            })->count() + 1;
    }

    /**
     * @param  list<string>|null  $hints  Precomputed {@see waitlistStaffHints()} to avoid duplicate work in the same request.
     * @return array{tone: 'ok'|'warn'|'danger', headline: string, hints: list<string>, diagnostics: array, resume_auto_sms_available: bool}
     */
    public function systemStatusBar(?array $hints = null): array
    {
        $hints = $hints ?? $this->waitlistStaffHints();
        $tone = 'ok';
        $headline = 'Auto table-ready SMS can run when a table frees and a guest fits.';
        $resumeAutoSmsAvailable = false;

        if (Setting::get('sms_enabled', '1') !== '1') {
            $tone = 'danger';
            $headline = 'SMS is disabled in settings — no texts will send.';
        } elseif (! AutomationSettings::bool('automation_notify_queue_on_release', true)) {
            $tone = 'warn';
            $headline = 'Auto table-ready SMS is turned off.';
            $resumeAutoSmsAvailable = true;
        } elseif (! AutomationSettings::effectivePeakForQueueNotify()) {
            $tone = 'warn';
            $headline = 'Outside busy hours — auto SMS paused (use Override or manual SMS).';
        } elseif (count($hints) > 0) {
            $tone = 'warn';
            $headline = $hints[0];
        }

        return [
            'tone' => $tone,
            'headline' => $headline,
            'hints' => $hints,
            'diagnostics' => AutomationSettings::queuePeakDiagnostics(),
            'resume_auto_sms_available' => $resumeAutoSmsAvailable,
        ];
    }

    /**
     * Prefer smallest capacity that still fits the party (minimize wasted seats).
     *
     * @param  \Illuminate\Support\Collection<int, Table>  $tables
     * @return \Illuminate\Support\Collection<int, Table>
     */
    public function sortTablesForParty(\Illuminate\Support\Collection $tables, QueueEntry $entry): \Illuminate\Support\Collection
    {
        return $tables->filter(fn (Table $t) => $this->tableFitsEntry($entry, $t))
            ->sortBy(function (Table $t) use ($entry) {
                $waste = $t->capacity - $entry->party_size;

                return [$waste, $t->capacity, $t->id];
            })
            ->values();
    }
}
