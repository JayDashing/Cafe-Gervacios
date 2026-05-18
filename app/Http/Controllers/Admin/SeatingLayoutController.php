<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\QueueEntry;
use App\Models\Seat;
use App\Models\Setting;
use App\Models\Table;
use Illuminate\Support\Collection;

class SeatingLayoutController extends Controller
{
    public const PLANNER_WIDTH = 1200;

    public const PLANNER_HEIGHT = 760;

    private const MERGE_GROUP_SETTING_KEY = 'floor_plan_merge_groups';

    /**
     * Guest name + party size for floor map badges (queue hold, booking, or walk-in).
     *
     * @param  Collection<int, Table>  $tables
     * @return Collection<int, array{guest: string, party: string, arrival_at: ?string}>
     */
    public static function floorTableGuestInfoForTables(Collection $tables): Collection
    {
        if ($tables->isEmpty()) {
            return collect();
        }

        $reservedEntries = QueueEntry::query()
            ->where('status', 'notified')
            ->whereNotNull('reserved_table_id')
            ->get()
            ->keyBy('reserved_table_id');

        $bookingsByTable = Booking::query()
            ->whereNotNull('table_id')
            ->whereIn('status', ['active', 'pending'])
            ->whereNull('no_show_at')
            ->orderByDesc('id')
            ->get()
            ->unique('table_id')
            ->keyBy('table_id');

        $tz = (string) config('app.timezone');

        $bookingIds = $tables->pluck('booking_id')->filter();
        $bookings = Booking::whereIn('id', $bookingIds)
            ->get()->keyBy('id');

        return $tables->mapWithKeys(function (Table $t) use ($reservedEntries, $bookingsByTable, $bookings, $tz) {
            $guest = '—';
            $party = (string) max(1, (int) $t->capacity);
            $arrival_at = null;

            if ($t->status === 'reserved') {
                $e = $reservedEntries->get($t->id);
                if ($e) {
                    $guest = trim((string) $e->customer_name) !== '' ? (string) $e->customer_name : '—';
                    $party = (string) max(1, (int) $e->party_size);
                } elseif ($t->booking_id !== null) {
                    $booking = $bookings->get($t->booking_id);
                    if ($booking) {
                        $guest = trim((string) $booking->customer_name) !== '' ? (string) $booking->customer_name : '—';
                        $party = (string) max(1, (int) $booking->party_size);
                        $arrival_at = $booking->booked_at !== null
                            ? $booking->booked_at->timezone($tz)->format('M d, g:i A')
                            : null;
                    } else {
                        $guest = 'Walk-in';
                    }
                } else {
                    $guest = 'Walk-in';
                }
            } elseif ($t->status === 'occupied') {
                $b = $bookingsByTable->get($t->id);
                if ($b) {
                    $guest = trim((string) $b->customer_name) !== '' ? (string) $b->customer_name : '—';
                    $party = (string) max(1, (int) $b->party_size);
                } else {
                    $guest = 'Walk-in';
                    $party = (string) max(1, (int) ($t->occupied_party ?? $t->capacity));
                }
            }

            return [
                $t->id => [
                    'guest' => $guest,
                    'party' => $party,
                    'arrival_at' => $arrival_at,
                ],
            ];
        });
    }

    /**
     * @return array{tableGroups: \Illuminate\Support\Collection, allSeats: \Illuminate\Support\Collection, floorTableGuestInfo: Collection<int, array{guest: string, party: string, arrival_at: ?string}>}
     */
    public static function layoutData(): array
    {
        $allSeats = Seat::query()
            ->with('table')
            ->orderBy('table_id')
            ->orderBy('seat_index')
            ->get();

        $tableGroups = Table::query()
            ->with(['seats' => fn ($q) => $q->orderBy('seat_index')])
            ->orderBy('id')
            ->get()
            ->map(function (Table $table) {
                if ($table->seats->isEmpty()) {
                    return null;
                }

                $anchorX = round((float) $table->seats->avg('pos_x'), 2);
                $anchorY = round((float) $table->seats->avg('pos_y'), 2);

                $seatList = $table->seats;
                $minX = (float) $seatList->min('pos_x');
                $maxX = (float) $seatList->max('pos_x');
                $minY = (float) $seatList->min('pos_y');
                $maxY = (float) $seatList->max('pos_y');
                $pad = 3.5;
                $left = max(0.0, $minX - $pad);
                $top = max(0.0, $minY - $pad);
                $w = max(12.0, min(100.0, $maxX - $minX + 2 * $pad));
                $h = max(10.0, min(100.0, $maxY - $minY + 2 * $pad));

                return (object) [
                    'table' => $table,
                    'anchor_x' => $anchorX,
                    'anchor_y' => $anchorY,
                    'seats' => $table->seats,
                    'bounds' => (object) [
                        'left' => round($left, 4),
                        'top' => round($top, 4),
                        'w' => round($w, 4),
                        'h' => round($h, 4),
                    ],
                ];
            })
            ->filter()
            ->values();

        $tablesForBadges = $tableGroups->pluck('table')->unique('id')->values();

        return [
            'tableGroups' => $tableGroups,
            'allSeats' => $allSeats,
            'floorTableGuestInfo' => self::floorTableGuestInfoForTables($tablesForBadges),
        ];
    }

    /**
     * @return array{plannerTables: array<int, array<string, mixed>>, plannerCanvas: array{width: int, height: int}}
     */
    public static function plannerData(): array
    {
        $tables = Table::query()
            ->with(['seats' => fn ($q) => $q->orderBy('seat_index')])
            ->orderBy('label')
            ->orderBy('id')
            ->get();

        $activeBookings = Booking::query()
            ->whereNotNull('table_id')
            ->whereIn('status', ['active', 'pending'])
            ->whereNull('no_show_at')
            ->orderByDesc('id')
            ->get()
            ->unique('table_id')
            ->keyBy('table_id');

        $bookingIds = $tables->pluck('booking_id')->filter();
        $bookingsById = $bookingIds->isEmpty()
            ? collect()
            : Booking::query()->whereIn('id', $bookingIds)->get()->keyBy('id');

        $rows = $tables->map(function (Table $table, int $index) use ($activeBookings, $bookingsById) {
            $booking = $table->booking_id
                ? $bookingsById->get($table->booking_id)
                : $activeBookings->get($table->id);

            return [
                'id' => $table->id,
                'label' => $table->label,
                'capacity' => (int) $table->capacity,
                'status' => (string) $table->status,
                'shape' => $thisShape = self::plannerShapeFor($table),
                'x' => self::plannerX($table, $index),
                'y' => self::plannerY($table, $index),
                'width' => self::plannerWidthFor($table, $thisShape),
                'height' => self::plannerHeightFor($table, $thisShape),
                'rotation' => (int) (($table->layout_rotation ?? 0) % 360),
                'seat_count' => (int) $table->seats->count(),
                'booking_id' => $table->booking_id ? (int) $table->booking_id : null,
                'booking' => $booking ? [
                    'id' => $booking->id,
                    'ref' => $booking->booking_ref,
                    'guest' => $booking->customer_name,
                    'party' => (int) $booking->party_size,
                    'status' => $booking->status,
                    'booked_at' => optional($booking->booked_at)->timezone(config('app.timezone'))->format('M d, g:i A'),
                ] : null,
            ];
        })->values()->all();

        return [
            'plannerTables' => $rows,
            'mergeGroups' => self::plannerMergeGroups($tables->pluck('id')->all()),
            'plannerCanvas' => [
                'width' => self::PLANNER_WIDTH,
                'height' => self::PLANNER_HEIGHT,
            ],
        ];
    }

    public static function plannerMergeGroups(array $validTableIds = []): array
    {
        $raw = Setting::get(self::MERGE_GROUP_SETTING_KEY, '[]');
        $decoded = json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $valid = array_fill_keys(array_map('intval', $validTableIds), true);
        $hasValidFilter = $validTableIds !== [];

        return collect($decoded)
            ->map(function ($group) use ($valid, $hasValidFilter) {
                if (! is_array($group)) {
                    return null;
                }

                $tableIds = collect($group['table_ids'] ?? $group['tableIds'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0 && (! $hasValidFilter || isset($valid[$id])))
                    ->unique()
                    ->values()
                    ->all();

                if (count($tableIds) < 2) {
                    return null;
                }

                $payload = [
                    'id' => (string) ($group['id'] ?? 'merge-'.implode('-', $tableIds)),
                    'table_ids' => $tableIds,
                ];

                foreach (['label', 'booking_ref', 'guest_name', 'source'] as $key) {
                    $value = trim((string) ($group[$key] ?? ''));
                    if ($value !== '') {
                        $payload[$key] = $value;
                    }
                }

                if (isset($group['booking_id']) && (int) $group['booking_id'] > 0) {
                    $payload['booking_id'] = (int) $group['booking_id'];
                }

                return $payload;
            })
            ->filter()
            ->values()
            ->all();
    }

    public static function savePlannerMergeGroups(array $groups): void
    {
        Setting::set(self::MERGE_GROUP_SETTING_KEY, json_encode(array_values($groups)));
    }

    public static function plannerShapeFor(Table $table): string
    {
        $shape = strtolower((string) ($table->planner_shape ?? ''));
        if (in_array($shape, ['square', 'rectangle', 'round', 'booth', 'counter'], true)) {
            return $shape;
        }

        $furniture = strtolower((string) ($table->furniture_type ?? ''));
        if ($furniture === 'booth') {
            return 'booth';
        }

        if (in_array($furniture, ['counter', 'bar', 'bar_stool'], true)) {
            return 'counter';
        }

        if ((string) $table->shape === 'circle') {
            return 'round';
        }

        return (int) $table->capacity >= 6 ? 'rectangle' : 'square';
    }

    private static function plannerX(Table $table, int $index): float
    {
        if ($table->position_x !== null) {
            return round((float) $table->position_x, 2);
        }

        if ($table->seats->isNotEmpty()) {
            return round(((float) $table->seats->avg('pos_x') / 100) * self::PLANNER_WIDTH, 2);
        }

        $col = $index % 5;

        return 80 + ($col * 170);
    }

    private static function plannerY(Table $table, int $index): float
    {
        if ($table->position_y !== null) {
            return round((float) $table->position_y, 2);
        }

        if ($table->seats->isNotEmpty()) {
            return round(((float) $table->seats->avg('pos_y') / 100) * self::PLANNER_HEIGHT, 2);
        }

        $row = intdiv($index, 5);

        return 80 + ($row * 145);
    }

    private static function plannerWidthFor(Table $table, string $shape): float
    {
        if ($table->layout_width !== null) {
            return round((float) $table->layout_width, 2);
        }

        return match ($shape) {
            'rectangle' => 180,
            'booth' => 168,
            'counter' => 78,
            'round' => 112,
            default => 120,
        };
    }

    private static function plannerHeightFor(Table $table, string $shape): float
    {
        if ($table->layout_height !== null) {
            return round((float) $table->layout_height, 2);
        }

        return match ($shape) {
            'rectangle' => 100,
            'booth' => 92,
            'counter' => 64,
            'round' => 112,
            default => 108,
        };
    }

    public function __invoke()
    {
        return redirect()->route('admin.tables', ['edit' => 1]);
    }
}
