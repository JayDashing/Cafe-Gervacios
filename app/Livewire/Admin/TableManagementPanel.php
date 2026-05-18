<?php

namespace App\Livewire\Admin;

use App\Models\Table;
use App\Services\TableService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

class TableManagementPanel extends Component
{
    public int $refreshVersion = 0;

    public string $statusFilter = 'all';

    public string $search = '';

    #[On('tables-refresh')]
    #[On('table-updated')]
    public function refreshFromTables(): void
    {
        $this->refreshVersion++;
    }

    public function markOccupied(int $tableId): void
    {
        $table = Table::query()->findOrFail($tableId);
        Gate::authorize('update', $table);

        try {
            if ($table->status === Table::STATUS_RESERVED && $table->booking_id !== null) {
                app(TableService::class)->checkIn($tableId);
                $message = 'Guest checked in.';
            } else {
                app(TableService::class)->seatWalkIn($tableId);
                $message = 'Walk-in seated.';
            }
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());

            return;
        } catch (\Illuminate\Database\QueryException $e) {
            report($e);
            $this->dispatch('notify', type: 'error', message: 'Invalid table status. Please refresh and try again.');

            return;
        }

        $this->dispatchOperationsRefresh();
        $this->dispatch('notify', type: 'success', message: $message);
    }

    public function markFree(int $tableId): void
    {
        $this->performTableAction($tableId, 'markFree', 'Table marked free.');
    }

    public function markCleaning(int $tableId): void
    {
        $this->performTableAction($tableId, 'sendToCleaning', 'Table moved to cleaning.');
    }

    public function releaseTable(int $tableId): void
    {
        $this->performTableAction($tableId, 'releaseTable', 'Table released.');
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

    private function performTableAction(int $tableId, string $method, string $successMessage): void
    {
        $table = Table::query()->findOrFail($tableId);
        Gate::authorize('update', $table);

        if (! in_array($method, ['markFree', 'sendToCleaning', 'releaseTable'], true)) {
            return;
        }

        try {
            app(TableService::class)->{$method}($tableId);
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());

            return;
        } catch (\Illuminate\Database\QueryException $e) {
            report($e);
            $this->dispatch('notify', type: 'error', message: 'Invalid table status. Please refresh and try again.');

            return;
        }

        $this->dispatchOperationsRefresh();
        $this->dispatch('notify', type: 'success', message: $successMessage);
    }

    private function dispatchOperationsRefresh(): void
    {
        $this->refreshVersion++;
        $this->dispatch('tables-refresh');
        $this->dispatch('table-updated');
        $this->dispatch('queue-updated');
        $this->dispatch('eta-recalculated');
    }

}
