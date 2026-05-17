<?php

namespace App\Livewire\Admin;

use App\Models\Table;
use App\Services\TableService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Table-level actions (release, status, furniture) for the seat map — same behavior as the old TableGrid popover.
 */
class TableQuickActions extends Component
{
    public ?int $selectedTableId = null;

    public float $popoverLeft = 0.0;

    public float $popoverTop = 0.0;

    /** Bumped on every tables-refresh so the modal wire:key changes and DOM matches DB. */
    public int $tablesSyncVersion = 0;

    #[On('tables-refresh')]
    public function refreshFromTables(): void
    {
        $this->tablesSyncVersion++;
    }

    public function pollTableModal(): void
    {
    }

    #[On('table-ops-select')]
    public function syncSelection(mixed $tableId = null, mixed $left = null, mixed $top = null): void
    {
        if (is_array($tableId)) {
            $tid = $tableId['tableId'] ?? null;
            $this->selectedTableId = $tid === null || $tid === '' ? null : (int) $tid;
            if ($this->selectedTableId === null) {
                $this->popoverLeft = 0.0;
                $this->popoverTop = 0.0;

                return;
            }
            if (array_key_exists('left', $tableId)) {
                $this->popoverLeft = (float) $tableId['left'];
            }
            if (array_key_exists('top', $tableId)) {
                $this->popoverTop = (float) $tableId['top'];
            }

            return;
        }
        if ($tableId === null || $tableId === '') {
            $this->selectedTableId = null;
            $this->popoverLeft = 0.0;
            $this->popoverTop = 0.0;

            return;
        }
        $this->selectedTableId = (int) $tableId;
        if ($left !== null && $left !== '') {
            $this->popoverLeft = (float) $left;
        } else {
            $this->popoverLeft = 0.0;
        }
        if ($top !== null && $top !== '') {
            $this->popoverTop = (float) $top;
        } else {
            $this->popoverTop = 0.0;
        }
    }

    public function clearSelection(): void
    {
        $this->selectedTableId = null;
        $this->popoverLeft = 0.0;
        $this->popoverTop = 0.0;
        $this->dispatch('table-ops-select', tableId: null);
    }

    /**
     * Single status dropdown: uses release() for guests-left→cleaning and markReadyAfterCleaning for cleaning→free when needed.
     */
    public function applyStatusFromSelect(int $tableId, string $newStatus): void
    {
        $table = Table::findOrFail($tableId);
        Gate::authorize('update', $table);

        if (! in_array($newStatus, ['available', 'occupied', 'reserved', 'unavailable', 'cleaning'], true)) {
            return;
        }

        if ($table->status === $newStatus) {
            return;
        }

        if ($table->status === 'occupied' && $newStatus === 'cleaning') {
            $this->release($tableId);

            return;
        }

        if ($table->status === 'cleaning' && $newStatus === 'available') {
            $this->markReadyAfterCleaning($tableId);

            return;
        }

        $this->overrideStatus($tableId, $newStatus);
    }

    public function overrideStatus(int $tableId, string $status): void
    {
        $table = Table::findOrFail($tableId);
        Gate::authorize('update', $table);

        app(TableService::class)->override($tableId, $status);
        Cache::forget('tables.venue.1');
        $this->tablesSyncVersion++;
        $this->dispatch('tables-refresh');
        $this->dispatch('notify', type: 'success', message: $this->statusMessage($status));
    }

    public function release(int $tableId): void
    {
        $table = Table::findOrFail($tableId);
        Gate::authorize('update', $table);

        app(TableService::class)->release($tableId);
        Cache::forget('tables.venue.1');
        $this->tablesSyncVersion++;
        $this->dispatch('tables-refresh');
        $this->dispatch('notify', type: 'success', message: 'Table moved to cleaning.');
    }

    public function markReadyAfterCleaning(int $tableId): void
    {
        $table = Table::findOrFail($tableId);
        Gate::authorize('update', $table);

        if ($table->status !== 'cleaning') {
            return;
        }

        app(TableService::class)->override($tableId, 'available');
        Cache::forget('tables.venue.1');
        $this->tablesSyncVersion++;
        $this->dispatch('tables-refresh');
        $this->dispatch('notify', type: 'success', message: 'Table marked free.');
    }

    public function updateFurnitureType(int $tableId, string $furnitureType): void
    {
        $table = Table::findOrFail($tableId);
        Gate::authorize('update', $table);

        if (!in_array($furnitureType, ['standard', 'sofa', 'stool', 'bar_stool'], true)) {
            return;
        }

        $table->update(['furniture_type' => $furnitureType]);
        Cache::forget('tables.venue.1');
        $this->tablesSyncVersion++;
        $this->dispatch('tables-refresh');
    }

    public function formatOccupancyTimer(Table $table): string
    {
        if (!$table->occupied_at) {
            return '';
        }
        $elapsed = now()->diffInMinutes($table->occupied_at);

        return sprintf('%02d:%02d', intdiv($elapsed, 60), $elapsed % 60);
    }

    public function render()
    {
        $table = null;
        $partyDisplay = null;
        $arrivalDisplay = null;
        $seatedDisplay = null;

        if ($this->selectedTableId) {
            $table = Table::query()->find($this->selectedTableId);
            if ($table) {
                $table->refresh();
                $tz = (string) config('app.timezone');
                if ($table->status === 'occupied') {
                    $partyDisplay = (string) max(1, (int) ($table->occupied_party ?? $table->capacity));
                    $seatedDisplay = $table->occupied_at !== null
                        ? $table->occupied_at->timezone($tz)->format('M d, g:i A')
                        : null;
                } elseif ($table->status === 'reserved') {
                    $table->loadMissing('booking');
                    if ($table->booking_id !== null && $table->booking) {
                        $partyDisplay = (string) max(1, (int) $table->booking->party_size);
                        $arrivalDisplay = $table->booking->booked_at !== null
                            ? $table->booking->booked_at->timezone($tz)->format('M d, g:i A')
                            : '--';
                    } else {
                        $partyDisplay = (string) max(1, (int) $table->capacity);
                        $arrivalDisplay = '--';
                    }
                } else {
                    $partyDisplay = (string) max(1, (int) $table->capacity);
                }
            } else {
                $this->selectedTableId = null;
            }
        }

        return view('livewire.admin.table-quick-actions', [
            'table' => $table,
            'partyDisplay' => $partyDisplay,
            'arrivalDisplay' => $arrivalDisplay,
            'seatedDisplay' => $seatedDisplay,
        ]);
    }

    private function statusMessage(string $status): string
    {
        return match ($status) {
            'available' => 'Table marked free.',
            'reserved' => 'Table reserved.',
            'occupied' => 'Table marked occupied.',
            'cleaning' => 'Table moved to cleaning.',
            default => 'Table updated.',
        };
    }
}
