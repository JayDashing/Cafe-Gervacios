<?php

namespace App\Services;

use App\Models\QueueEntry;
use App\Models\Setting;
use App\Models\Table;
use App\Jobs\SendSmsJob;
use App\Mail\QueueHoldExpiredMail;
use App\Mail\TableReadyMail;
use App\Models\AutomationLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class QueueService
{
    private const BASE_WAIT_PER_QUEUE_POSITION = 10;

    /**
     * Why the waitlist might not auto-advance when a table is Free (for staff UI).
     *
     * @return list<string>
     */
    public function waitlistStaffHints(): array
    {
        $hints = [];

        if (!AutomationSettings::bool('automation_notify_queue_on_release', true)) {
            $hints[] = 'Auto table-ready alerts are turned off. Use manual notify for each guest when a table suits them.';
        }

        if (Setting::get('sms_enabled', '1') !== '1') {
            $hints[] = 'Guest notifications are disabled in settings.';
        }

        $waiting = QueueEntry::waiting()
            ->orderByDesc('priority_score')
            ->orderByRaw('COALESCE(estimated_wait, 999999)')
            ->orderBy('joined_at')
            ->get();
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
            $hints[] = 'No Free table fits the next guest(s) by party size (and accessible-table rule if enabled for PWD in settings). Free a suitable table or notify them manually.';
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
        ?string $deviceType = null,
        ?string $email = null
    ): QueueEntry {
        $phone = trim((string) $phone);
        $email = trim((string) $email);

        $priority = app(PriorityService::class);
        $score = $priority->getScore($priorityType);

        $entry = DB::transaction(function () use ($name, $phone, $email, $partySize, $priorityType, $priority, $score, $source, $deviceType) {
            $max = QueueEntry::whereDate('joined_at', today())
                ->lockForUpdate()
                ->max('queue_display_number');

            $displayNumber = (int) ($max ?? 0) + 1;

            return QueueEntry::create([
                'customer_name' => $name,
                'customer_phone' => $phone,
                'customer_email' => $email !== '' ? $email : null,
                'party_size' => $partySize,
                'priority_type' => $priorityType,
                'priority_score' => $score,
                'needs_accessible' => $priority->requiresAccessibleTable($priorityType),
                'estimated_wait' => 0,
                'last_estimated_wait' => 0,
                'joined_at' => now(),
                'source' => $source,
                'device_type' => $deviceType,
                'queue_display_number' => $displayNumber,
            ]);
        });

        $this->refreshEstimatedWaits();
        $entry->refresh();

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
        $this->refreshEstimatedWaits();
    }

    public function seatAutomatically(int $entryId): void
    {
        $entry = QueueEntry::findOrFail($entryId);

        if (!in_array($entry->status, ['waiting', 'notified'], true)) {
            throw new \InvalidArgumentException('This waitlist entry cannot be seated (wrong status).');
        }

        $table = null;

        if ($entry->reserved_table_id !== null) {
            $held = Table::query()->find($entry->reserved_table_id);
            if (
                $held !== null
                && in_array($held->status, ['reserved', 'available'], true)
                && $this->tableFitsEntry($entry, $held)
            ) {
                $table = $held;
            }
        }

        if ($table === null) {
            $table = $this->sortTablesForParty(
                Table::query()
                    ->where('status', 'available')
                    ->where('capacity', '>=', $entry->party_size)
                    ->orderBy('capacity')
                    ->orderBy('id')
                    ->get(),
                $entry
            )->first();
        }

        if ($table === null) {
            throw new \InvalidArgumentException('No free table fits this party.');
        }

        $this->seat($entryId, (int) $table->id);
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

        $this->refreshEstimatedWaits();
    }

    /**
     * When a notified guest's hold expires: free the held table, cancel the entry, email, notify next.
     */
    public function finalizeExpiredNotifiedHold(QueueEntry $entry): void
    {
        $name = $entry->customer_name;
        $email = trim((string) $entry->customer_email);
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

        $payload = ['entry_id' => $entryId];
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            Mail::to($email, $name)->send(new QueueHoldExpiredMail(
                customerName: $name,
                venueName: config('app.venue_name', config('app.name')),
            ));
            $payload['notification_channel'] = 'email';
            $payload['notification_status'] = 'sent';
            $payload['email_domain'] = str_contains($email, '@') ? substr(strrchr($email, '@'), 1) : null;
        } else {
            $payload['notification_channel'] = 'email';
            $payload['notification_status'] = 'skipped';
            $payload['notification_reason'] = 'missing_guest_email';
        }

        AutomationLog::record('queue_holds', 'Skipped hold expired', $payload);
        $this->notifyNextAfterTableRelease();
        $this->refreshEstimatedWaits();
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
        $this->refreshEstimatedWaits();

        if (!AutomationSettings::bool('automation_notify_queue_on_release', true)) {
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
            $email = trim((string) $entry->customer_email);
            $phone = trim((string) $entry->customer_phone);

            if ($phone === '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

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
                'estimated_wait' => 0,
                'last_estimated_wait' => 0,
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

        $email = trim((string) $next->customer_email);
        $isEmail = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;

        $this->sendTableReadyNotification($next, $assignedTable, $holdMinutes, $venueName);
        AutomationLog::record('queue_notify', 'Guest auto-notified for available table', [
            'entry_id' => $next->id,
            'table_id' => $assignedTable->id,
            'notification_channel' => $isEmail ? 'email' : 'sms',
            'notification_status' => $isEmail ? 'sent' : 'queued',
        ]);

        Cache::forget('tables.venue.1');
        $this->refreshEstimatedWaits();
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

            if (trim((string) $entry->customer_phone) === '' && trim((string) $entry->customer_email) === '') {
                throw new \InvalidArgumentException('This guest has no phone number or email for notification.');
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
                'estimated_wait' => 0,
                'last_estimated_wait' => 0,
            ];

            if ($entry->hold_confirmation_code === null || $entry->hold_confirmation_code === '') {
                $attrs['hold_confirmation_code'] = strtoupper(Str::random(6));
            }

            $entry->update($attrs);
            $reservedTableId = (int) $table->id;
        });

        $entry = QueueEntry::query()->findOrFail($entryId);
        $table = $reservedTableId !== null ? Table::query()->find($reservedTableId) : null;

        $this->sendTableReadyNotification($entry, $table, $holdMinutes, $venueName);

        Cache::forget('tables.venue.1');
        $this->refreshEstimatedWaits();
        $entry->refresh();

        return $entry;
    }


    private function sendTableReadyNotification(QueueEntry $entry, ?Table $table, int $holdMinutes, string $venueName): void
    {
        $tableLabel = $table ? ' Table '.$table->label.'.' : '';
        $code = (string) ($entry->hold_confirmation_code ?? '');
        $email = trim((string) $entry->customer_email);

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            Mail::to($email, (string) $entry->customer_name)->send(new TableReadyMail(
                customerName: (string) $entry->customer_name,
                venueName: $venueName,
                tableLabel: $table?->label,
                holdMinutes: $holdMinutes,
                confirmationCode: $code,
            ));

            Log::info('Waitlist table-ready email sent', [
                'entry_id' => $entry->id,
                'email_domain' => str_contains($email, '@') ? substr(strrchr($email, '@'), 1) : null,
            ]);

            return;
        }

        $phone = trim((string) $entry->customer_phone);
        if ($phone === '') {
            throw new \InvalidArgumentException('This guest has no phone number or email for notification.');
        }

        dispatch(new SendSmsJob($phone, 'table_ready', [
            'name' => $entry->customer_name,
            'venue' => $venueName.($table ? ' - Table '.$table->label : ''),
            'is_priority' => $entry->isPriority(),
            'minutes' => (string) $holdMinutes,
            'code' => $code,
        ]));
    }
    public function estimateWait(int $partySize, string $priorityType = 'none'): int
    {
        $priority = app(PriorityService::class);
        $entry = new QueueEntry([
            'party_size' => $partySize,
            'priority_type' => $priorityType,
            'priority_score' => $priority->getScore($priorityType),
            'needs_accessible' => $priority->requiresAccessibleTable($priorityType),
            'status' => 'waiting',
            'joined_at' => now(),
        ]);

        $waiting = QueueEntry::waiting()->get()->push($entry);
        $estimates = $this->calculateWaitEstimates($waiting);

        return $estimates[$this->entryEstimateKey($entry)] ?? self::BASE_WAIT_PER_QUEUE_POSITION;
    }

    /**
     * Recalculate saved ETA values for the active waiting queue.
     *
     * @return array<int, int> queue entry id => ETA minutes
     */
    public function refreshEstimatedWaits(): array
    {
        $waiting = QueueEntry::waiting()->get();
        $estimates = $this->calculateWaitEstimates($waiting);

        foreach ($waiting as $entry) {
            $new = $estimates[$this->entryEstimateKey($entry)] ?? self::BASE_WAIT_PER_QUEUE_POSITION;
            if ((int) ($entry->estimated_wait ?? -1) === $new
                && (int) ($entry->last_estimated_wait ?? -1) === $new) {
                continue;
            }

            QueueEntry::query()
                ->whereKey($entry->id)
                ->update([
                    'estimated_wait' => $new,
                    'last_estimated_wait' => $new,
                ]);
        }

        return collect($estimates)
            ->filter(fn ($value, $key) => is_int($key))
            ->all();
    }

    /**
     * @param  Collection<int, QueueEntry>  $waiting
     * @return array<int|string, int>
     */
    private function calculateWaitEstimates(Collection $waiting): array
    {
        if ($waiting->isEmpty()) {
            return [];
        }

        $waiting = $this->sortWaitingEntries($waiting);
        $availableTables = Table::query()
            ->where('status', 'available')
            ->orderBy('capacity')
            ->orderBy('id')
            ->get()
            ->values();
        $allTables = Table::query()
            ->orderBy('capacity')
            ->orderBy('id')
            ->get()
            ->values();

        $estimates = [];

        foreach ($waiting as $entry) {
            $availableIndex = $availableTables->search(fn (Table $table) => $this->tableFitsEntry($entry, $table));

            if ($availableIndex !== false) {
                $estimates[$this->entryEstimateKey($entry)] = 0;
                $availableTables->forget($availableIndex);
                $availableTables = $availableTables->values();

                continue;
            }

            $ahead = $this->compatibleWaitingGroupsAhead($entry, $waiting, $allTables);
            $estimates[$this->entryEstimateKey($entry)] = min(
                65535,
                max(self::BASE_WAIT_PER_QUEUE_POSITION, ($ahead + 1) * self::BASE_WAIT_PER_QUEUE_POSITION)
            );
        }

        return $estimates;
    }

    /**
     * @param  Collection<int, QueueEntry>  $entries
     */
    private function sortWaitingEntries(Collection $entries): Collection
    {
        return $entries->sort(function (QueueEntry $a, QueueEntry $b) {
            $priority = (int) ($b->priority_score ?? 0) <=> (int) ($a->priority_score ?? 0);
            if ($priority !== 0) {
                return $priority;
            }

            $aTime = ($a->joined_at ?? $a->created_at ?? now())->getTimestamp();
            $bTime = ($b->joined_at ?? $b->created_at ?? now())->getTimestamp();
            if ($aTime !== $bTime) {
                return $aTime <=> $bTime;
            }

            return (int) ($a->id ?? PHP_INT_MAX) <=> (int) ($b->id ?? PHP_INT_MAX);
        })->values();
    }

    /**
     * @param  Collection<int, QueueEntry>  $waiting
     * @param  Collection<int, Table>  $allTables
     */
    private function compatibleWaitingGroupsAhead(QueueEntry $target, Collection $waiting, Collection $allTables): int
    {
        $count = 0;

        foreach ($waiting as $entry) {
            if ($this->sameQueueEntry($entry, $target)) {
                break;
            }

            if ($this->entriesCompeteForTables($target, $entry, $allTables)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  Collection<int, Table>  $allTables
     */
    private function entriesCompeteForTables(QueueEntry $target, QueueEntry $ahead, Collection $allTables): bool
    {
        if ($allTables->isEmpty()) {
            return true;
        }

        $targetTables = $allTables->filter(fn (Table $table) => $this->tableFitsEntry($target, $table));

        if ($targetTables->isEmpty()) {
            return true;
        }

        return $targetTables->contains(fn (Table $table) => $this->tableFitsEntry($ahead, $table));
    }

    private function sameQueueEntry(QueueEntry $a, QueueEntry $b): bool
    {
        if ($a->exists && $b->exists) {
            return (int) $a->id === (int) $b->id;
        }

        return $a === $b;
    }

    private function entryEstimateKey(QueueEntry $entry): int|string
    {
        return $entry->exists ? (int) $entry->id : 'new';
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
        $headline = 'Auto table-ready alerts can run when a table frees and a guest fits.';
        $resumeAutoSmsAvailable = false;

        if (Setting::get('sms_enabled', '1') !== '1') {
            $tone = 'danger';
            $headline = 'Guest notifications are disabled in settings.';
        } elseif (! AutomationSettings::bool('automation_notify_queue_on_release', true)) {
            $tone = 'warn';
            $headline = 'Auto table-ready alerts are turned off.';
            $resumeAutoSmsAvailable = true;
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
