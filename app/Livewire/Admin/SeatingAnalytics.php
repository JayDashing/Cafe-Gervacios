<?php

namespace App\Livewire\Admin;

use App\Models\Booking;
use App\Models\QueueEntry;
use App\Models\Table;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SeatingAnalytics extends Component
{
    #[Computed]
    public function totalBookingsToday(): int
    {
        return Booking::query()->whereDate('booked_at', today())->count();
    }

    #[Computed]
    public function totalCheckedInToday(): int
    {
        return Booking::query()->whereDate('checked_in_at', today())->count();
    }

    #[Computed]
    public function totalSeatedFromQueue(): int
    {
        return QueueEntry::query()->whereDate('seated_at', today())->count();
    }

    #[Computed]
    public function tablesOccupiedNow(): int
    {
        return Table::query()->where('status', 'occupied')->count();
    }

    #[Computed]
    public function tablesFreeNow(): int
    {
        return Table::query()->where('status', 'available')->count();
    }

    /**
     * SQL expression for hour-of-day of `booked_at` (0–23), driver-specific.
     */
    private function hourOfBookedAtExpression(): string
    {
        return match (Booking::query()->getConnection()->getDriverName()) {
            'mysql' => 'HOUR(booked_at)',
            'pgsql' => 'EXTRACT(HOUR FROM booked_at)::integer',
            'sqlite' => "CAST(strftime('%H', booked_at) AS INTEGER)",
            default => 'HOUR(booked_at)',
        };
    }

    /**
     * @return array<int, int> hour 0–23 => count (bookings in last 7 days)
     */
    #[Computed]
    public function peakHourData(): array
    {
        $since = now()->subDays(7)->startOfDay();
        $counts = array_fill(0, 24, 0);

        $hourExpr = $this->hourOfBookedAtExpression();

        $rows = Booking::query()
            ->whereNotNull('booked_at')
            ->where('booked_at', '>=', $since)
            ->whereIn('status', ['active', 'completed'])
            ->selectRaw("{$hourExpr} as hr, COUNT(*) as cnt")
            ->groupBy(DB::raw($hourExpr))
            ->get();

        foreach ($rows as $row) {
            $h = (int) $row->hr;
            if ($h >= 0 && $h <= 23) {
                $counts[$h] = (int) $row->cnt;
            }
        }

        return $counts;
    }

    /**
     * @return list<array{label: string, count: int}>
     */
    #[Computed]
    public function topTableUsage(): array
    {
        $since = now()->subDays(30)->startOfDay();

        $rows = Booking::query()
            ->join('tables', 'bookings.table_id', '=', 'tables.id')
            ->whereNotNull('bookings.table_id')
            ->where('bookings.booked_at', '>=', $since)
            ->selectRaw('tables.label as label, COUNT(*) as cnt')
            ->groupBy('tables.id', 'tables.label')
            ->orderByDesc('cnt')
            ->limit(5)
            ->get();

        return $rows->map(static fn ($row) => [
            'label' => (string) $row->label,
            'count' => (int) $row->cnt,
        ])->values()->all();
    }

    /**
     * @return list<string> 24 labels 12AM–11PM style
     */
    #[Computed]
    public function peakHourLabels(): array
    {
        $labels = [];
        for ($h = 0; $h < 24; $h++) {
            $labels[] = Carbon::createFromTime($h, 0, 0)->format('g A');
        }

        return $labels;
    }

    #[Computed]
    public function hasSourceData(): bool
    {
        return Booking::query()->exists()
            || QueueEntry::query()->exists()
            || Table::query()->exists();
    }

    public function render()
    {
        return view('livewire.admin.seating-analytics', [
            'chartPayload' => [
                'peak' => $this->peakHourData,
                'peakLabels' => $this->peakHourLabels,
                'top' => $this->topTableUsage,
            ],
        ]);
    }
}
