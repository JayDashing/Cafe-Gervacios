<?php

namespace App\Livewire\Admin;

use App\Models\AutomationLog;
use App\Models\Booking;
use App\Models\QueueEntry;
use App\Models\Setting;
use App\Models\Table;
use App\Services\AutomationSettings;
use App\Services\BookingNoShowNotifier;
use App\Services\QueueService;
use App\Services\TableService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * WaitlistPanel
 *
 * Displays the sorted waitlist with priority and regular sections.
 * Priority customers appear first with visual divider.
 * Staff can seat, notify, or cancel queue entries.
 *
 * RA 9994 / RA 7277 — priority entries always shown at top of queue.
 */
class WaitlistPanel extends Component
{
    /**
     * Pre-select "Seat at" when staff picks a table on the floor (same party fit rules apply).
     */
    public ?int $selectedTableId = null;

    public ?int $seatQuickPickEntryId = null;

    public ?int $floorSeatTableId = null;

    public ?int $highlightedQueueEntryId = null;

    public bool $showBusyHoursModal = false;

    public bool $showWalkInModal = false;

    public int $walkInModalKey = 0;

    public int $operationsRefreshVersion = 0;

    public string $activeTab = 'waiting';

    public string $search = '';

    public string $priorityFilter = 'all';

    public string $partySizeFilter = 'all';

    public string $busyPeakStart = '17:00';

    public string $busyPeakEnd = '22:00';

    public bool $busyLearnFromQueue = true;

    /** @var array<int, string> */
    public array $holdCode = [];

    /** @var array<int, int> Selected table id per queue entry (notified + hold code flow). */
    public array $seatTablePick = [];

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, ['waiting', 'notified', 'seated', 'cancelled'], true)) {
            return;
        }

        $this->activeTab = $tab;
    }

    #[On('table-selected')]
    public function syncTableSelection(?int $tableId): void
    {
        $this->selectedTableId = $tableId;
    }

    public function openSeatQuickPick(int $entryId): void
    {
        $this->ensureStaff();
        $this->seatQuickPickEntryId = $entryId;
    }

    public function closeSeatQuickPick(): void
    {
        $this->seatQuickPickEntryId = null;
    }

    #[On('floor-map-seat-waitlist-guest')]
    public function openFloorMapSeatWaitlistGuest(mixed $tableId = null): void
    {
        $this->ensureStaff();

        if (is_array($tableId)) {
            $tableId = $tableId['tableId'] ?? null;
        }

        $id = (int) $tableId;
        if ($id <= 0) {
            $this->dispatch('notify', type: 'error', message: 'Choose a table marker first.');

            return;
        }

        $table = Table::query()->find($id);
        if (! $table) {
            $this->dispatch('notify', type: 'error', message: 'Table marker was not found.');

            return;
        }

        if ($table->status !== 'available') {
            $this->dispatch('notify', type: 'error', message: 'Choose a free table before seating a waitlist guest.');

            return;
        }

        $this->floorSeatTableId = $table->id;
        $this->selectedTableId = $table->id;
    }

    public function closeFloorMapSeatModal(): void
    {
        $this->floorSeatTableId = null;
    }

    public function highlightCompatibleTablesForEntry(int $entryId): void
    {
        $this->ensureStaff();

        $entry = QueueEntry::query()->findOrFail($entryId);
        if (! in_array($entry->status, ['waiting', 'notified'], true)) {
            return;
        }

        $this->highlightedQueueEntryId = $entry->id;

        $tableIds = Table::query()
            ->where('status', 'available')
            ->orderBy('capacity')
            ->orderBy('id')
            ->get()
            ->filter(fn (Table $table) => $entry->accommodates($table))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $this->dispatch(
            'operations-highlight-compatible-tables',
            entryId: $entry->id,
            guest: $entry->customer_name,
            partySize: (int) $entry->party_size,
            tableIds: $tableIds,
        );
    }

    public function openWalkInModal(): void
    {
        $this->ensureStaff();
        $this->showWalkInModal = true;
    }

    public function closeWalkInModal(): void
    {
        $this->showWalkInModal = false;
        $this->walkInModalKey++;
    }

    #[On('walk-in-registration-completed')]
    public function completeWalkInRegistration(): void
    {
        $this->showWalkInModal = false;
        $this->walkInModalKey++;
        $this->dispatchOperationsRefresh('walkin-created');
    }

    #[On('tables-refresh')]
    #[On('table-updated')]
    #[On('queue-updated')]
    #[On('guest-seated')]
    #[On('sms-sent')]
    #[On('reservation-updated')]
    #[On('eta-recalculated')]
    public function refreshOperationsState(): void
    {
        // Re-render after floor-map status changes so ETA, queue state, and table choices stay in sync.
        $this->operationsRefreshVersion++;
    }

    public function seatFromQuickPick(int $entryId, int $tableId): void
    {
        $this->ensureStaff();
        $entry = QueueEntry::find($entryId);
        if ($entry && filled($entry->hold_confirmation_code)) {
            $this->confirmAndSeat($entryId, $tableId);
        } else {
            $this->seatCustomer($entryId, $tableId);
        }
        $this->closeSeatQuickPick();
    }

    public function seatWaitingGuestAtFloorTable(int $entryId): void
    {
        $this->ensureStaff();

        $tableId = (int) $this->floorSeatTableId;
        if ($tableId <= 0) {
            $this->dispatch('notify', type: 'error', message: 'Choose a table marker first.');

            return;
        }

        $entry = QueueEntry::query()->findOrFail($entryId);
        $table = Table::query()->findOrFail($tableId);

        if ($entry->status !== 'waiting') {
            $this->dispatch('notify', type: 'error', message: 'Use the confirmation code flow for notified guests.');

            return;
        }

        if ($table->status !== 'available') {
            $this->dispatch('notify', type: 'error', message: 'Choose a free table before seating a waitlist guest.');

            return;
        }

        if (! $entry->accommodates($table)) {
            $this->dispatch('notify', type: 'error', message: 'This table is not compatible with the selected guest.');

            return;
        }

        if ($this->seatCustomer($entry->id, $table->id)) {
            $this->closeFloorMapSeatModal();
        }
    }

    public function confirmAndSeatFromSeatButton(int $entryId): void
    {
        $this->ensureStaff();
        $this->resetErrorBag(['holdCode.'.$entryId, 'seatCustomer']);

        $entry = QueueEntry::query()->findOrFail($entryId);
        Gate::authorize('update', $entry);

        if (filled($entry->hold_confirmation_code)) {
            $entered = trim((string) ($this->holdCode[$entryId] ?? ''));
            $expected = (string) $entry->hold_confirmation_code;
            if (strcasecmp($entered, $expected) !== 0) {
                $this->addError('holdCode.'.$entryId, 'Incorrect code.');
                $this->dispatch('notify', type: 'error', message: 'Confirmation code does not match.');

                return;
            }
        }

        if ($this->seatCustomerAutomatically($entryId)) {
            unset($this->holdCode[$entryId], $this->seatTablePick[$entryId]);
        }
    }

    public function confirmAndSeat(int $entryId, int $tableId): void
    {
        $this->resetErrorBag(['holdCode.'.$entryId]);

        $entry = QueueEntry::findOrFail($entryId);
        Gate::authorize('update', $entry);

        if (filled($entry->hold_confirmation_code)) {
            $entered = trim((string) ($this->holdCode[$entryId] ?? ''));
            $expected = (string) $entry->hold_confirmation_code;
            if (strcasecmp($entered, $expected) !== 0) {
                $this->addError('holdCode.'.$entryId, 'Incorrect code.');
                $this->dispatch('notify', type: 'error', message: 'Confirmation code does not match.');

                return;
            }
        }

        if ($this->seatCustomer($entryId, $tableId)) {
            unset($this->holdCode[$entryId], $this->seatTablePick[$entryId]);
        }
    }

    public function openBusyHoursModal(): void
    {
        $this->ensureAdmin();
        $this->busyPeakStart = (string) Setting::get('peak_hours_start', config('automation.peak_hours_start', '17:00'));
        $this->busyPeakEnd = (string) Setting::get('peak_hours_end', config('automation.peak_hours_end', '22:00'));
        $this->busyLearnFromQueue = Setting::get('peak_hours_learn_from_queue', config('automation.peak_hours_learn_from_queue', true) ? '1' : '0') === '1';
        $this->showBusyHoursModal = true;
    }

    public function closeBusyHoursModal(): void
    {
        $this->showBusyHoursModal = false;
    }

    public function saveBusyHours(): void
    {
        $this->ensureAdmin();
        $this->validate([
            'busyPeakStart' => 'required|date_format:H:i',
            'busyPeakEnd' => 'required|date_format:H:i',
        ]);

        Setting::set('peak_hours_start', $this->busyPeakStart);
        Setting::set('peak_hours_end', $this->busyPeakEnd);
        Setting::set('peak_hours_learn_from_queue', $this->busyLearnFromQueue ? '1' : '0');
        AutomationSettings::forgetDynamicPeakQueueHoursCache();
        $this->showBusyHoursModal = false;
        $this->dispatch('notify', type: 'success', message: 'Busy hours saved.');
    }

    public function toggleAutoSms(): void
    {
        $this->ensureAdmin();
        $v = Setting::get('automation_notify_queue_on_release', '1');
        Setting::set('automation_notify_queue_on_release', $v === '1' ? '0' : '1');
        $this->dispatch('notify', type: 'success', message: 'Auto table-ready SMS setting updated.');
    }

    /**
     * Turn auto table-ready SMS back on (same setting as the Auto-SMS control / waitlist toggle).
     */
    public function resumeAutoSms(): void
    {
        $this->ensureAdmin();
        Setting::set('automation_notify_queue_on_release', '1');
        $this->dispatch('notify', type: 'success', message: 'Auto table-ready SMS resumed.');
    }

    public function togglePeakOverride(): void
    {
        $this->ensureStaff();
        $v = Setting::get('waitlist_staff_peak_override', '0');
        Setting::set('waitlist_staff_peak_override', $v === '1' ? '0' : '1');
        $this->dispatch('notify', type: 'info', message: $v === '1' ? 'Staff override off.' : 'Staff override on — auto SMS can run outside busy hours.');
    }

    public function extendHold(int $entryId): void
    {
        $entry = QueueEntry::findOrFail($entryId);
        Gate::authorize('update', $entry);

        if ($entry->status !== 'notified' || $entry->hold_expires_at === null) {
            return;
        }

        $entry->hold_expires_at = $entry->hold_expires_at->copy()->addMinutes(5);
        $entry->save();
        $this->dispatchOperationsRefresh('hold-extended');
        $this->dispatch('notify', type: 'success', message: 'Hold extended by 5 minutes.');
    }

    public function render()
    {
        $queueService = app(QueueService::class);
        $hints = $queueService->waitlistStaffHints();

        $priorityQueue = QueueEntry::waiting()->where('priority_score', 100)->sorted()->get();
        $regularQueue = QueueEntry::waiting()->where('priority_score', 0)->sorted()->get();
        $waitingGuests = $priorityQueue->concat($regularQueue)->values();

        $notifiedHold = QueueEntry::query()
            ->where('status', 'notified')
            ->orderByDesc('notified_at')
            ->get();

        $seatedGuests = QueueEntry::query()
            ->where('status', 'seated')
            ->latest('seated_at')
            ->latest('updated_at')
            ->take(8)
            ->get();

        $summary = [
            'waiting' => $waitingGuests->count(),
            'notified' => $notifiedHold->count(),
            'seated_today' => QueueEntry::query()
                ->where('status', 'seated')
                ->whereDate('seated_at', now()->toDateString())
                ->count(),
            'cancelled_today' => QueueEntry::query()
                ->where('status', 'cancelled')
                ->whereDate('updated_at', now()->toDateString())
                ->count(),
        ];

        $cancelledGuests = QueueEntry::query()
            ->where('status', 'cancelled')
            ->latest('updated_at')
            ->take(8)
            ->get();

        $availableTables = Table::query()
            ->whereIn('status', ['available', 'reserved'])
            ->orderBy('capacity')
            ->orderBy('id')
            ->get();

        $noShowBookings = Booking::query()
            ->whereIn('status', ['active', 'pending'])
            ->whereNull('checked_in_at')
            ->whereNull('no_show_at')
            ->orderBy('booked_at')
            ->get();

        $systemStatus = $queueService->systemStatusBar($hints);

        $quickPickEntry = null;
        $sortedQuickTables = collect();
        if ($this->seatQuickPickEntryId) {
            $quickPickEntry = QueueEntry::find($this->seatQuickPickEntryId);
            if ($quickPickEntry) {
                $sortedQuickTables = $queueService->sortTablesForParty($availableTables, $quickPickEntry);
            }
        }

        $floorSeatTable = null;
        $floorSeatCandidates = collect();
        if ($this->floorSeatTableId) {
            $floorSeatTable = Table::query()->find($this->floorSeatTableId);
            if ($floorSeatTable && $floorSeatTable->status === 'available') {
                $floorSeatCandidates = QueueEntry::query()
                    ->where('status', 'waiting')
                    ->sorted()
                    ->get()
                    ->filter(fn (QueueEntry $entry) => $entry->accommodates($floorSeatTable))
                    ->values();
            }
        }

        $autoSmsOn = Setting::get('automation_notify_queue_on_release', '1') === '1';
        $peakOverrideOn = Setting::get('waitlist_staff_peak_override', '0') === '1';

        return view('livewire.admin.waitlist-panel', [
            'priorityQueue' => $priorityQueue,
            'regularQueue' => $regularQueue,
            'waitingGuests' => $this->filterEntries($waitingGuests),
            'notifiedHold' => $notifiedHold,
            'filteredNotifiedGuests' => $this->filterEntries($notifiedHold),
            'seatedGuests' => $seatedGuests,
            'filteredSeatedGuests' => $this->filterEntries($seatedGuests),
            'cancelledGuests' => $cancelledGuests,
            'filteredCancelledGuests' => $this->filterEntries($cancelledGuests),
            'summary' => $summary,
            'waitlistHints' => $hints,
            'availableTables' => $availableTables,
            'noShowBookings' => $noShowBookings,
            'systemStatus' => $systemStatus,
            'quickPickEntry' => $quickPickEntry,
            'sortedQuickTables' => $sortedQuickTables,
            'floorSeatTable' => $floorSeatTable,
            'floorSeatCandidates' => $floorSeatCandidates,
            'autoSmsOn' => $autoSmsOn,
            'peakOverrideOn' => $peakOverrideOn,
        ]);
    }

    private function filterEntries($entries)
    {
        $term = str($this->search)->trim()->lower()->toString();
        $priority = strtolower($this->priorityFilter);
        $party = strtolower($this->partySizeFilter);
        return $entries->filter(function (QueueEntry $entry) use ($term, $priority, $party) {
            if ($priority === 'priority' && ! $entry->isPriority()) {
                return false;
            }

            if ($priority === 'standard' && $entry->isPriority()) {
                return false;
            }

            if (in_array($priority, ['pwd', 'senior', 'pregnant'], true)
                && strtolower((string) $entry->priority_type) !== $priority) {
                return false;
            }

            $size = (int) $entry->party_size;
            if ($party === '1-2' && ($size < 1 || $size > 2)) {
                return false;
            }
            if ($party === '3-4' && ($size < 3 || $size > 4)) {
                return false;
            }
            if ($party === '5-plus' && $size < 5) {
                return false;
            }

            if ($term === '') {
                return true;
            }

            return str_contains(strtolower((string) $entry->customer_name), $term)
                || str_contains(strtolower((string) $entry->customer_phone), $term)
                || str_contains(strtolower((string) $entry->customer_email), $term)
                || str_contains(strtolower((string) $entry->source), $term)
                || str_contains((string) $entry->queue_display_number, $term);
        })->values();
    }

    public function seatCustomer(int $entryId, mixed $tableId): bool
    {
        $this->resetErrorBag('seatCustomer');

        if ($tableId === null || $tableId === '' || (int) $tableId <= 0) {
            $this->addError('seatCustomer', 'Please select a table first.');
            $this->dispatch('notify', type: 'error', message: 'Please select a table first.');

            return false;
        }

        $entry = QueueEntry::findOrFail($entryId);
        Gate::authorize('update', $entry);

        if ($entry->status === 'cancelled') {
            $this->dispatch('notify', type: 'error', message: 'This hold has expired. Please re-add the guest to the queue.');

            return false;
        }

        try {
            app(QueueService::class)->seat($entryId, (int) $tableId);
            $this->selectedTableId = null;
            $this->dispatchOperationsRefresh('guest-seated');
            $this->dispatch('table-selected', tableId: null);
            $this->dispatch('notify', type: 'success', message: 'Guest seated.');

            return true;
        } catch (\Throwable $e) {
            $this->addError('seatCustomer', $e->getMessage());
            $this->dispatch('notify', type: 'error', message: $e->getMessage());

            return false;
        }
    }

    public function seatCustomerAutomatically(int $entryId): bool
    {
        $this->resetErrorBag('seatCustomer');

        $entry = QueueEntry::findOrFail($entryId);
        Gate::authorize('update', $entry);

        if ($entry->status === 'cancelled') {
            $this->dispatch('notify', type: 'error', message: 'This hold has expired. Please re-add the guest to the queue.');

            return false;
        }

        try {
            app(QueueService::class)->seatAutomatically($entryId);
            $this->selectedTableId = null;
            $this->dispatchOperationsRefresh('guest-seated');
            $this->dispatch('table-selected', tableId: null);
            $this->dispatch('notify', type: 'success', message: 'Guest seated.');

            return true;
        } catch (\Throwable $e) {
            $this->addError('seatCustomer', $e->getMessage());
            $this->dispatch('notify', type: 'error', message: $e->getMessage());

            return false;
        }
    }

    public function cancelEntry(int $entryId): void
    {
        $entry = QueueEntry::findOrFail($entryId);
        Gate::authorize('delete', $entry);

        app(QueueService::class)->cancel($entryId);
        $this->dispatchOperationsRefresh();
        $this->dispatch('notify', type: 'info', message: 'Removed from queue.');
    }

    public function sendSmsManually(int $entryId): void
    {
        $entry = QueueEntry::findOrFail($entryId);
        Gate::authorize('update', $entry);

        try {
            app(QueueService::class)->notifyEntryManually($entryId);
            $this->dispatchOperationsRefresh('sms-sent');
            $this->dispatch('notify', type: 'success', message: 'Notification sent.');
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    /**
     * Manual no-show: same side effects as AutomationEngine::markNoShows() for one booking
     * (free table, cancel + no_show_at, notify guest, automation log, notify queue).
     */
    public function markBookingNoShow(int $bookingId): void
    {
        $user = auth()->user();
        if (! $user || ! in_array($user->role, ['admin', 'staff'], true)) {
            abort(403);
        }

        $booking = Booking::findOrFail($bookingId);

        if ($booking->payment_status === 'pending' && $booking->status === 'pending') {
            $this->addError('markBookingNoShow', 'Unpaid pending reservations cannot be marked as no-show.');

            return;
        }

        if (! in_array($booking->status, ['active', 'pending'], true)
            || $booking->checked_in_at !== null
            || $booking->no_show_at !== null) {
            $this->addError('markBookingNoShow', 'This reservation cannot be marked as no-show.');

            return;
        }

        $tableId = $booking->table_id;
        if ($tableId) {
            try {
                app(TableService::class)->noShow((int) $tableId);
            } catch (\Throwable) {
                // ignore — matches AutomationEngine::markNoShows()
            }
        }

        $booking->update([
            'status' => 'cancelled',
            'no_show_at' => now(),
            'table_id' => null,
        ]);

        $notification = app(BookingNoShowNotifier::class)->send($booking);

        AutomationLog::record('no_shows', 'No-show marked', [
            'booking_id' => $booking->id,
            'notification' => $notification,
        ]);
        app(QueueService::class)->notifyNextAfterTableRelease();
        $this->dispatchOperationsRefresh('reservation-updated');
        $this->dispatch('notify', type: 'warning', message: 'Marked as no-show.');
    }

    private function dispatchOperationsRefresh(string ...$extraEvents): void
    {
        $this->operationsRefreshVersion++;

        $events = array_unique([
            'queue-updated',
            'tables-refresh',
            'table-updated',
            'eta-recalculated',
            ...$extraEvents,
        ]);

        foreach ($events as $event) {
            $this->dispatch($event);
        }
    }

    private function ensureStaff(): void
    {
        $u = auth()->user();
        if (! $u || ! in_array($u->role, ['admin', 'staff'], true)) {
            abort(403);
        }
    }

    private function ensureAdmin(): void
    {
        if (! auth()->user()?->isAdmin()) {
            abort(403);
        }
    }
}
