<?php

namespace App\Livewire\Admin;

use App\Models\Table;
use App\Services\TableService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

class TableManagementPanel extends Component
{
    public int $refreshVersion = 0;

    public string $statusFilter = 'all';

    public string $search = '';

    #[On('tables-refresh')]
    public function refreshFromTables(): void
    {
        $this->refreshVersion++;
    }

    public function markOccupied(int $tableId): void
    {
        $this->applyStatus($tableId, 'occupied');
    }

    public function markFree(int $tableId): void
    {
        $this->applyStatus($tableId, 'available');
    }

    public function markReserved(int $tableId): void
    {
        $this->applyStatus($tableId, 'reserved');
    }

    public function markCleaning(int $tableId): void
    {
        $table = Table::query()->findOrFail($tableId);
        Gate::authorize('update', $table);

        if ($table->status !== 'occupied') {
            $this->dispatch('notify', type: 'info', message: 'Only occupied tables can be moved to cleaning.');

            return;
        }

        app(TableService::class)->release($tableId);
        Cache::forget('tables.venue.1');
        $this->refreshVersion++;
        $this->dispatch('tables-refresh');
        $this->dispatch('notify', type: 'success', message: 'Table moved to cleaning.');
    }

    public function setStatusFilter(string $status): void
    {
        if (! in_array($status, ['all', 'available', 'reserved', 'occupied', 'cleaning'], true)) {
            return;
        }

        $this->statusFilter = $status;
    }

    public function render()
    {
        $allTables = Table::query()
            ->with([
                'booking:id,booking_ref,customer_name,party_size,booked_at,status,table_id',
            ])
            ->withCount('seats')
            ->orderBy('label')
            ->orderBy('id')
            ->get();

        $query = trim(strtolower($this->search));

        $tables = $allTables
            ->filter(function (Table $table) use ($query) {
                if ($this->statusFilter !== 'all' && $table->status !== $this->statusFilter) {
                    return false;
                }

                if ($query === '') {
                    return true;
                }

                $booking = $table->booking;
                $haystack = strtolower(implode(' ', [
                    $table->label,
                    '#'.$table->id,
                    $booking?->booking_ref,
                    $booking?->customer_name,
                ]));

                return str_contains($haystack, $query);
            })
            ->values();

        return view('livewire.admin.table-management-panel', [
            'tables' => $tables,
            'allTables' => $allTables,
        ]);
    }

    private function applyStatus(int $tableId, string $status): void
    {
        $table = Table::query()->findOrFail($tableId);
        Gate::authorize('update', $table);

        if (! in_array($status, ['available', 'occupied', 'reserved'], true)) {
            return;
        }

        if ($table->status === $status) {
            $this->dispatch('notify', type: 'info', message: 'Table is already '.$this->statusLabel($status).'.');

            return;
        }

        if ($status === 'available' && $table->status === 'occupied') {
            $this->dispatch('notify', type: 'error', message: 'Occupied tables must be checked out before they can be marked free.');

            return;
        }

        if ($status === 'occupied' && $table->status === 'cleaning') {
            $this->dispatch('notify', type: 'error', message: 'Mark the table ready before seating guests again.');

            return;
        }

        if ($status === 'reserved' && $table->status !== 'available') {
            $this->dispatch('notify', type: 'error', message: 'Only free tables can be marked reserved.');

            return;
        }

        app(TableService::class)->override($tableId, $status);
        Cache::forget('tables.venue.1');
        $this->refreshVersion++;
        $this->dispatch('tables-refresh');
        $this->dispatch('notify', type: 'success', message: $this->statusMessage($status));
    }

    private function statusMessage(string $status): string
    {
        return match ($status) {
            'available' => 'Table marked free.',
            'occupied' => 'Table marked occupied.',
            'reserved' => 'Table marked reserved.',
            default => 'Table updated.',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'available' => 'free',
            'occupied' => 'occupied',
            'reserved' => 'reserved',
            default => 'updated',
        };
    }
}
