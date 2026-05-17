<?php

namespace App\Livewire\Admin;

use App\Http\Controllers\Admin\SeatingLayoutController;
use Livewire\Component;

class DashboardSeatMap extends Component
{
    /**
     * What a plain click on a seat marker does: edit dots | waitlist table | table status modal.
     */
    public string $seatClickMode = 'edit';

    public function setSeatClickMode(string $mode): void
    {
        if (!in_array($mode, ['edit', 'waitlist', 'table'], true)) {
            return;
        }

        $this->seatClickMode = $mode;

        $this->dispatch('table-selected', tableId: null);
        $this->dispatch('table-ops-select', tableId: null);
    }

    public function render()
    {
        $layoutData = SeatingLayoutController::layoutData();
        $tableCounts = [
            'available' => 0,
            'reserved' => 0,
            'occupied' => 0,
            'cleaning' => 0,
        ];

        foreach ($layoutData['tableGroups'] as $group) {
            $status = (string) ($group->table->status ?? 'available');
            if (! array_key_exists($status, $tableCounts)) {
                $status = 'available';
            }
            $tableCounts[$status]++;
        }

        return view('livewire.admin.dashboard-seat-map', array_merge(
            $layoutData,
            [
                'seatClickMode' => $this->seatClickMode,
                'tableStatusCounts' => $tableCounts,
            ]
        ));
    }
}
