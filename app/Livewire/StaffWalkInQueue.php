<?php

namespace App\Livewire;

use App\Http\Controllers\Admin\SeatingLayoutController;
use App\Livewire\Concerns\WithToastNotifications;
use App\Models\AdminLog;
use App\Models\QueueEntry;
use App\Models\Setting;
use App\Models\Table;
use App\Rules\PhilippinePhone;
use App\Services\BookingGuardService;
use App\Services\PriorityService;
use App\Services\QueueService;
use Livewire\Attributes\On;
use Livewire\Component;

class StaffWalkInQueue extends Component
{
    use WithToastNotifications;

    public string $customer_name = '';

    public string $customer_phone = '';

    public string $customer_email = '';

    public $party_size = '2';

    public string $priority_type = 'none';

    public ?int $selectedTableId = null;

    public bool $modalMode = false;

    public string $wizardStep = 'details';

    public ?string $wizardAction = null;

    public ?string $successTitle = null;

    public ?string $successDetail = null;

    /**
     * @var array<int, array{label: string, value: string}>
     */
    public array $successSummary = [];

    public int $tableRefreshVersion = 0;

    #[On('tables-refresh')]
    #[On('table-updated')]
    public function refreshWalkInTableChoices(): void
    {
        $this->tableRefreshVersion++;
        $this->clearInvalidSelectedTable();
    }

    public function submitGuidedAction(): void
    {
        if ($this->compatibleFreeTableCount() > 0) {
            if ($this->selectedTableId === null) {
                $this->toastError('Select an available table to seat guest.');

                return;
            }

            $this->seatSelectedTable(true);

            return;
        }

        $this->register();
    }

    public function continueToTableSelection(): void
    {
        $this->validatedWalkInDetails();
        $this->wizardAction = $this->compatibleFreeTableCount() > 0 ? 'seat' : 'waitlist';
        $this->wizardStep = 'selection';
    }

    public function continueToReview(?string $action = null): void
    {
        $this->validatedWalkInDetails();

        $this->wizardAction = $action ?: ($this->compatibleFreeTableCount() > 0 ? 'seat' : 'waitlist');

        if ($this->wizardAction === 'seat' && $this->selectedTableId === null) {
            $this->toastError('Select an available table to seat guest.');

            return;
        }

        $this->wizardStep = 'review';
    }

    public function backToGuestDetails(): void
    {
        $this->wizardStep = 'details';
    }

    public function backToTableSelection(): void
    {
        $this->wizardStep = 'selection';
    }

    public function confirmWizardAction(): void
    {
        $this->wizardAction = $this->selectedTableId !== null
            ? 'seat'
            : ($this->wizardAction ?: 'waitlist');

        if ($this->wizardAction === 'seat') {
            if ($this->selectedTableId === null) {
                $this->toastError('Please select a free compatible table first.');

                return;
            }

            $this->seatSelectedTable(true);

            return;
        }

        $this->register();
    }

    public function finishWizard(): void
    {
        $this->dispatch('walk-in-registration-completed');
    }

    public function startAnotherWalkIn(): void
    {
        $this->resetWalkInForm();
        $this->wizardStep = 'details';
        $this->successTitle = null;
        $this->successDetail = null;
        $this->successSummary = [];
    }

    public function register(): void
    {
        $validated = $this->validatedWalkInDetails();

        if (! $this->ensurePhoneIsAvailable()) {
            return;
        }

        try {
            $entry = $this->createQueueEntry($validated);
        } catch (\InvalidArgumentException $e) {
            $this->toastError($e->getMessage());

            return;
        }

        AdminLog::record('staff_queue_register', 'queue_entry', $entry->id, 'Walk-in registered at host');

        $this->dispatchOperationsRefresh('walkin-created');

        $this->completeWalkInAction(
            'Guest added to waitlist.',
            'Queue #'.$entry->queue_display_number.' - Estimated wait: '.$entry->waitEstimateLabel().'.',
            [
                ['label' => 'Guest', 'value' => (string) $entry->customer_name],
                ['label' => 'Queue', 'value' => '#'.(string) $entry->queue_display_number],
                ['label' => 'Party', 'value' => (int) $entry->party_size.' guests'],
                ['label' => 'ETA', 'value' => $entry->waitEstimateLabel()],
                ['label' => 'Notify', 'value' => filled($entry->customer_email) ? 'Email' : (filled($entry->customer_phone) ? 'SMS' : 'Disabled')],
            ]
        );
        $this->toastSuccess('Added to queue. Ticket #'.$entry->queue_display_number);
    }

    public function seatSelectedTable(bool $closeModalOnSuccess = false): void
    {
        $validated = $this->validatedWalkInDetails();

        if ($this->selectedTableId === null) {
            $this->toastError('Please select a free compatible table first.');

            return;
        }

        $table = Table::query()->find($this->selectedTableId);
        if (! $table || $this->tableSelectionIssue($table, (int) $validated['party_size']) !== null) {
            $this->selectedTableId = null;
            $this->toastError('This table is not available for the selected party size.');

            return;
        }

        if (! $this->ensurePhoneIsAvailable()) {
            return;
        }

        try {
            $entry = $this->createQueueEntry($validated);
            app(QueueService::class)->seat($entry->id, $table->id);
        } catch (\Throwable $e) {
            $this->toastError($e->getMessage());

            return;
        }

        AdminLog::record('staff_queue_seat_now', 'queue_entry', $entry->id, 'Walk-in seated from host floor map at '.$table->label);

        $this->dispatchOperationsRefresh('walkin-created', 'guest-seated');

        $this->completeWalkInAction(
            'Guest seated at '.$table->label.'.',
            'Party of '.(int) $entry->party_size.' seated immediately.',
            [
                ['label' => 'Guest', 'value' => (string) $entry->customer_name],
                ['label' => 'Party', 'value' => (int) $entry->party_size.' guests'],
                ['label' => 'Table', 'value' => (string) $table->label],
                ['label' => 'Notify', 'value' => filled($entry->customer_email) ? 'Email' : (filled($entry->customer_phone) ? 'SMS' : 'Disabled')],
            ],
            $closeModalOnSuccess
        );
        $this->toastSuccess('Guest seated at table '.$table->label.'.');
    }

    public function selectTable(int $tableId): void
    {
        $table = Table::query()->find($tableId);
        if (! $table) {
            $this->selectedTableId = null;
            $this->toastError('This table is not available for the selected party size.');

            return;
        }

        $issue = $this->tableSelectionIssue($table);
        if ($issue !== null) {
            $this->toastError('This table is not available for the selected party size.');

            return;
        }

        $this->selectedTableId = $table->id;
        $this->toastSuccess('Table '.$table->label.' selected.');
    }

    public function clearSelectedTable(): void
    {
        $this->selectedTableId = null;
    }

    public function incrementPartySize(): void
    {
        $this->party_size = (string) min(20, $this->partySizeValue() + 1);
        $this->clearInvalidSelectedTable();
    }

    public function decrementPartySize(): void
    {
        $this->party_size = (string) max(1, $this->partySizeValue() - 1);
        $this->clearInvalidSelectedTable();
    }

    public function updatedPartySize(): void
    {
        $this->clearInvalidSelectedTable();
    }

    public function updatedPriorityType(): void
    {
        $this->clearInvalidSelectedTable();
    }

    public function render()
    {
        $layoutData = SeatingLayoutController::layoutData();
        $floorplanRelative = Setting::get('floorplan_image', 'images/floorplan.png');
        $floorplanPath = public_path($floorplanRelative);
        $hasFloorplan = is_file($floorplanPath);
        $selectedTable = $this->selectedTableId
            ? Table::query()->find($this->selectedTableId)
            : null;

        return view('livewire.staff-walk-in-queue', [
            'priorityQueue' => QueueEntry::waiting()->where('priority_score', '>', 0)->sorted()->get(),
            'regularQueue' => QueueEntry::waiting()->where('priority_score', 0)->sorted()->get(),
            'floorplanUrl' => $hasFloorplan ? asset($floorplanRelative).'?v='.filemtime($floorplanPath) : '',
            'hasFloorplan' => $hasFloorplan,
            'walkInTableMarkers' => $this->walkInTableMarkers($layoutData['tableGroups']),
            'selectedTable' => $selectedTable,
            'accessibleRequired' => $this->accessibleRequired(),
            'estimatedWait' => app(QueueService::class)->estimateWait($this->partySizeValue(), $this->priority_type),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedWalkInDetails(): array
    {
        $this->customer_name = trim($this->customer_name);
        $this->customer_phone = trim($this->customer_phone);
        $this->customer_email = trim($this->customer_email);

        return $this->validate([
            'customer_name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\s\-\.]+$/'],
            'customer_phone' => ['nullable', new PhilippinePhone],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'party_size' => ['required', 'integer', 'min:1', 'max:20'],
            'priority_type' => ['required', 'in:none,pwd,pregnant,senior'],
        ], [
            'customer_name.required' => 'Name is required.',
            'customer_name.regex' => 'Name may only contain letters, spaces, hyphens, and periods.',
            'party_size.required' => 'Party size is required.',
            'party_size.integer' => 'Party size must be a whole number.',
            'party_size.min' => 'Party size must be at least 1.',
            'party_size.max' => 'Party size cannot be more than 20.',
        ]);
    }

    private function ensurePhoneIsAvailable(): bool
    {
        if (filled($this->customer_phone) && app(BookingGuardService::class)->hasActiveEntry($this->customer_phone)) {
            $this->toastError('This phone already has an active booking or queue entry.');

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function createQueueEntry(array $validated): QueueEntry
    {
        return app(QueueService::class)->join(
            $this->customer_name,
            $this->customer_phone,
            (int) $validated['party_size'],
            $this->priority_type,
            'staff',
            'desktop',
            $this->customer_email
        );
    }

    private function resetWalkInForm(): void
    {
        $this->reset(['customer_name', 'customer_phone', 'customer_email', 'party_size', 'priority_type', 'selectedTableId', 'wizardAction']);
        $this->party_size = '2';
        $this->priority_type = 'none';
    }

    /**
     * @param  array<int, array{label: string, value: string}>  $summary
     */
    private function completeWalkInAction(string $title, string $detail, array $summary = [], bool $closeModal = false): void
    {
        $this->resetWalkInForm();

        if ($this->modalMode && ! $closeModal) {
            $this->wizardStep = 'success';
            $this->successTitle = $title;
            $this->successDetail = $detail;
            $this->successSummary = $summary;

            return;
        }

        $this->dispatch('walk-in-registration-completed');
    }

    private function dispatchOperationsRefresh(string ...$extraEvents): void
    {
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

    private function clearInvalidSelectedTable(): void
    {
        if ($this->selectedTableId === null) {
            return;
        }

        $table = Table::query()->find($this->selectedTableId);
        if (! $table || $this->tableSelectionIssue($table) !== null) {
            $this->selectedTableId = null;
        }
    }

    private function accessibleRequired(): bool
    {
        return app(PriorityService::class)->requiresAccessibleTable($this->priority_type);
    }

    private function partySizeValue(): int
    {
        $size = (int) $this->party_size;

        return max(1, min(20, $size > 0 ? $size : 1));
    }

    private function tableSelectionIssue(Table $table, ?int $partySize = null): ?string
    {
        $partySize ??= $this->partySizeValue();

        if ($table->status !== 'available') {
            return 'status';
        }

        if ((int) $table->capacity < $partySize) {
            return 'capacity';
        }

        if ($this->accessibleRequired() && ! $table->is_accessible) {
            return 'accessibility';
        }

        return null;
    }

    private function tableStatusLabel(string $status): string
    {
        return match ($status) {
            'reserved' => 'RESERVED',
            'occupied' => 'OCCUPIED',
            'cleaning' => 'CLEANING',
            default => 'FREE',
        };
    }

    private function tableDisabledReason(Table $table, ?string $issue): string
    {
        return match ($issue) {
            'status' => $this->tableStatusLabel((string) $table->status),
            'capacity' => 'Too small',
            'accessibility' => 'Needs accessible',
            default => 'Selectable',
        };
    }

    private function compatibleFreeTableCount(): int
    {
        return Table::query()
            ->where('status', 'available')
            ->whereHas('seats')
            ->get()
            ->filter(fn (Table $table) => $this->tableSelectionIssue($table) === null)
            ->count();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $tableGroups
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function walkInTableMarkers($tableGroups)
    {
        return $tableGroups->map(function (object $group) {
            /** @var Table $table */
            $table = $group->table;
            $issue = $this->tableSelectionIssue($table);

            return [
                'id' => (int) $table->id,
                'label' => (string) $table->label,
                'status' => (string) $table->status,
                'status_label' => $this->tableStatusLabel((string) $table->status),
                'capacity' => (int) $table->capacity,
                'is_accessible' => (bool) $table->is_accessible,
                'x' => (float) $group->anchor_x,
                'y' => (float) $group->anchor_y,
                'selectable' => $issue === null,
                'reason' => $this->tableDisabledReason($table, $issue),
                'selected' => (int) $this->selectedTableId === (int) $table->id,
            ];
        })->values();
    }
}
