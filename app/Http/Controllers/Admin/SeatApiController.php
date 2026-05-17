<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Seat;
use App\Models\Table;
use App\Services\TableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SeatApiController extends Controller
{
    private const MERGE_DISTANCE_LIMIT = 18.0;

    public function index(): JsonResponse
    {
        $seats = Seat::query()
            ->with('table:id,label,capacity,furniture_type')
            ->orderBy('table_id')
            ->orderBy('seat_index')
            ->get();

        return response()->json([
            'seats' => $seats->map(fn (Seat $s) => [
                'id' => $s->id,
                'table_id' => $s->table_id,
                'table_label' => $s->table->label,
                'table_capacity' => (int) $s->table->capacity,
                'furniture_type' => $s->table->furniture_type ?? 'standard',
                'seat_index' => $s->seat_index,
                'status' => $s->status,
                'pos_x' => (float) $s->pos_x,
                'pos_y' => (float) $s->pos_y,
            ]),
        ]);
    }

    public function plannerIndex(): JsonResponse
    {
        return response()->json(SeatingLayoutController::plannerData());
    }

    public function plannerStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:50'],
            'capacity' => ['required', 'integer', 'min:1', 'max:99'],
            'planner_shape' => ['required', 'string', 'in:square,rectangle,round,booth,counter'],
            'position_x' => ['required', 'numeric', 'min:0', 'max:5000'],
            'position_y' => ['required', 'numeric', 'min:0', 'max:5000'],
            'layout_width' => ['required', 'numeric', 'min:40', 'max:600'],
            'layout_height' => ['required', 'numeric', 'min:40', 'max:600'],
            'layout_rotation' => ['nullable', 'integer', 'min:0', 'max:359'],
        ]);

        $result = DB::transaction(function () use ($validated) {
            $label = trim((string) ($validated['label'] ?? ''));
            if ($label === '') {
                $label = $this->nextDefaultTableLabel();
            }

            $shape = (string) $validated['planner_shape'];

            $table = Table::query()->create([
                'venue_id' => 1,
                'label' => $label,
                'capacity' => (int) $validated['capacity'],
                'status' => 'available',
                'shape' => $this->legacyShape($shape),
                'planner_shape' => $shape,
                'furniture_type' => $this->furnitureTypeForPlannerShape($shape),
                'position_x' => round((float) $validated['position_x'], 2),
                'position_y' => round((float) $validated['position_y'], 2),
                'layout_width' => round((float) $validated['layout_width'], 2),
                'layout_height' => round((float) $validated['layout_height'], 2),
                'layout_rotation' => (int) ($validated['layout_rotation'] ?? 0),
            ]);

            Seat::query()->create([
                'table_id' => $table->id,
                'seat_index' => 1,
                'status' => 'free',
                'pos_x' => $this->plannerPxToSeatPercent((float) $validated['position_x'], SeatingLayoutController::PLANNER_WIDTH),
                'pos_y' => $this->plannerPxToSeatPercent((float) $validated['position_y'], SeatingLayoutController::PLANNER_HEIGHT),
            ]);

            return $table->id;
        });

        return response()->json([
            'ok' => true,
            'table_id' => $result,
            'planner' => SeatingLayoutController::plannerData(),
        ]);
    }

    public function plannerSave(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tables' => ['required', 'array', 'min:1'],
            'tables.*.id' => ['required', 'integer', 'exists:tables,id'],
            'tables.*.label' => ['required', 'string', 'max:50'],
            'tables.*.capacity' => ['required', 'integer', 'min:1', 'max:99'],
            'tables.*.planner_shape' => ['required', 'string', 'in:square,rectangle,round,booth,counter'],
            'tables.*.position_x' => ['required', 'numeric', 'min:0', 'max:5000'],
            'tables.*.position_y' => ['required', 'numeric', 'min:0', 'max:5000'],
            'tables.*.layout_width' => ['required', 'numeric', 'min:40', 'max:600'],
            'tables.*.layout_height' => ['required', 'numeric', 'min:40', 'max:600'],
            'tables.*.layout_rotation' => ['required', 'integer', 'min:0', 'max:359'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['tables'] as $row) {
                $table = Table::query()->lockForUpdate()->findOrFail((int) $row['id']);
                $seatCount = $table->seats()->count();
                $capacity = (int) $row['capacity'];

                if ($capacity < $seatCount) {
                    throw ValidationException::withMessages([
                        'capacity' => ["{$table->label} capacity must be at least its mapped seat count ({$seatCount})."],
                    ]);
                }

                $shape = (string) $row['planner_shape'];

                $table->update([
                    'label' => trim((string) $row['label']),
                    'capacity' => $capacity,
                    'shape' => $this->legacyShape($shape),
                    'planner_shape' => $shape,
                    'furniture_type' => $this->furnitureTypeForPlannerShape($shape),
                    'position_x' => round((float) $row['position_x'], 2),
                    'position_y' => round((float) $row['position_y'], 2),
                    'layout_width' => round((float) $row['layout_width'], 2),
                    'layout_height' => round((float) $row['layout_height'], 2),
                    'layout_rotation' => (int) $row['layout_rotation'] % 360,
                ]);
            }
        });

        return response()->json([
            'ok' => true,
            'planner' => SeatingLayoutController::plannerData(),
        ]);
    }

    public function plannerStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table_id' => ['required', 'integer', 'exists:tables,id'],
            'status' => ['required', 'string', 'in:available,reserved,occupied,cleaning'],
        ]);

        $table = Table::query()->findOrFail((int) $validated['table_id']);
        Gate::authorize('update', $table);

        $status = (string) $validated['status'];

        if ($table->status === 'occupied' && $status === 'cleaning') {
            app(TableService::class)->release($table->id);
        } else {
            app(TableService::class)->override($table->id, $status);
        }

        Cache::forget('tables.venue.1');

        return response()->json([
            'ok' => true,
            'planner' => SeatingLayoutController::plannerData(),
        ]);
    }

    public function plannerDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table_id' => ['required', 'integer', 'exists:tables,id'],
        ]);

        $table = Table::query()->findOrFail((int) $validated['table_id']);

        if ($this->tableHasActiveBooking($table)) {
            throw ValidationException::withMessages([
                'table_id' => ['This table has an active booking and cannot be deleted.'],
            ]);
        }

        DB::transaction(function () use ($table) {
            $locked = Table::query()->lockForUpdate()->findOrFail($table->id);
            $locked->seats()->delete();
            $locked->delete();
        });

        return response()->json([
            'ok' => true,
            'planner' => SeatingLayoutController::plannerData(),
        ]);
    }

    public function plannerMergeGroups(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'groups' => ['nullable', 'array'],
            'groups.*.id' => ['required', 'string', 'max:80'],
            'groups.*.table_ids' => ['required', 'array', 'min:2'],
            'groups.*.table_ids.*' => ['required', 'integer', 'distinct', 'exists:tables,id'],
            'groups.*.label' => ['nullable', 'string', 'max:120'],
            'groups.*.booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
            'groups.*.booking_ref' => ['nullable', 'string', 'max:40'],
            'groups.*.guest_name' => ['nullable', 'string', 'max:120'],
            'groups.*.source' => ['nullable', 'string', 'max:40'],
        ]);

        $groups = collect($validated['groups'] ?? [])
            ->map(function (array $group) {
                $tableIds = collect($group['table_ids'])
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                $payload = [
                    'id' => (string) $group['id'],
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
            ->filter(fn (array $group) => count($group['table_ids']) >= 2)
            ->values()
            ->all();

        $this->assertMergeGroupsArePhysicallyValid($groups);

        SeatingLayoutController::savePlannerMergeGroups($groups);

        return response()->json([
            'ok' => true,
            'planner' => SeatingLayoutController::plannerData(),
        ]);
    }

    /**
     * Drop a new seat on the map at the given % position (creates a 1-seat table).
     * Capacity is guest/party size for bookings (may be &gt; 1 with a single marker until more dots are added).
     */
    public function place(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pos_x' => ['required', 'numeric', 'min:0', 'max:100'],
            'pos_y' => ['required', 'numeric', 'min:0', 'max:100'],
            'label' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'furniture_type' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', 'string', 'in:free,reserved,occupied'],
        ]);

        $result = DB::transaction(function () use ($validated) {
            $label = isset($validated['label']) && trim((string) $validated['label']) !== ''
                ? trim((string) $validated['label'])
                : $this->nextDefaultTableLabel();
            $this->assertTableLabelAvailable($label);

            $furniture = trim((string) ($validated['furniture_type'] ?? 'standard'));
            if ($furniture === '') {
                $furniture = 'standard';
            }

            $capacity = (int) ($validated['capacity'] ?? 1);

            $table = Table::query()->create([
                'venue_id' => 1,
                'label' => $label,
                'capacity' => $capacity,
                'status' => 'available',
                'shape' => 'rect',
                'furniture_type' => $furniture,
            ]);

            $seatStatus = isset($validated['status']) && $validated['status'] !== ''
                ? $validated['status']
                : 'free';

            $seat = Seat::query()->create([
                'table_id' => $table->id,
                'seat_index' => 1,
                'status' => $seatStatus,
                'pos_x' => round((float) $validated['pos_x'], 4),
                'pos_y' => round((float) $validated['pos_y'], 4),
            ]);

            return [
                'table_id' => $table->id,
                'table_label' => $table->label,
                'seat_id' => $seat->id,
                'pos_x' => (float) $seat->pos_x,
                'pos_y' => (float) $seat->pos_y,
            ];
        });

        return response()->json([
            'ok' => true,
            'seat' => $result,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'seat_id' => ['required', 'integer', 'exists:seats,id'],
            'status' => ['nullable', 'string', 'in:free,reserved,occupied'],
            'label' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'furniture_type' => ['nullable', 'string', 'max:32'],
            'pos_x' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pos_y' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $hasStatus = isset($validated['status']) && $validated['status'] !== null && $validated['status'] !== '';
        $hasLabel = $request->filled('label');
        $hasCapacity = $request->filled('capacity');
        $hasFurniture = $request->filled('furniture_type');
        $hasPosition = $request->filled('pos_x') && $request->filled('pos_y');

        if (! $hasStatus && ! $hasLabel && ! $hasCapacity && ! $hasFurniture && ! $hasPosition) {
            throw ValidationException::withMessages([
                'seat_id' => ['Choose a status and/or table details to save.'],
            ]);
        }

        $seat = Seat::query()->findOrFail($validated['seat_id']);
        $tableId = $seat->table_id;
        $table = Table::query()->findOrFail($tableId);

        DB::transaction(function () use ($table, $tableId, $validated, $hasStatus, $hasLabel, $hasCapacity, $hasFurniture, $hasPosition) {
            if ($hasStatus) {
                Seat::query()
                    ->where('table_id', $tableId)
                    ->update(['status' => $validated['status']]);
            }

            if ($hasPosition) {
                Seat::query()
                    ->where('table_id', $tableId)
                    ->update([
                        'pos_x' => round((float) $validated['pos_x'], 4),
                        'pos_y' => round((float) $validated['pos_y'], 4),
                    ]);
            }

            $meta = [];
            if ($hasLabel) {
                $label = trim((string) $validated['label']);
                $this->assertTableLabelAvailable($label, $tableId);
                $meta['label'] = $label;
            }
            if ($hasCapacity) {
                $seatCount = Seat::query()->where('table_id', $tableId)->count();
                $cap = (int) $validated['capacity'];
                if ($cap < $seatCount) {
                    throw ValidationException::withMessages([
                        'capacity' => ["Capacity must be at least the number of seats on the map ({$seatCount})."],
                    ]);
                }
                $meta['capacity'] = $cap;
            }
            if ($hasFurniture) {
                $ft = trim((string) $validated['furniture_type']);
                $meta['furniture_type'] = $ft === '' ? 'standard' : $ft;
            }

            if ($meta !== []) {
                $table->update($meta);
            }
        });

        $table->refresh();
        $seats = Seat::query()
            ->where('table_id', $tableId)
            ->orderBy('seat_index')
            ->get(['id', 'status', 'seat_index']);

        return response()->json([
            'ok' => true,
            'seats' => $seats->map(fn (Seat $s) => [
                'id' => $s->id,
                'status' => $s->status,
                'seat_index' => $s->seat_index,
                'pos_x' => (float) $s->pos_x,
                'pos_y' => (float) $s->pos_y,
            ])->values()->all(),
            'table' => [
                'id' => $table->id,
                'label' => $table->label,
                'capacity' => (int) $table->capacity,
                'furniture_type' => $table->furniture_type ?? 'standard',
            ],
        ]);
    }

    /**
     * Remove one seat dot, or the whole table (all dots). Blocked if the table has bookings.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'seat_id' => ['required', 'integer', 'exists:seats,id'],
            'scope' => ['required', 'string', 'in:seat,table'],
        ]);

        $seat = Seat::query()->findOrFail($validated['seat_id']);
        $table = Table::query()->findOrFail($seat->table_id);

        if ($table->bookings()->exists()) {
            throw ValidationException::withMessages([
                'seat_id' => ['This table has bookings and cannot be removed.'],
            ]);
        }

        $scope = $validated['scope'];
        $tableId = $table->id;

        DB::transaction(function () use ($seat, $table, $scope) {
            if ($scope === 'table') {
                $table->delete();

                return;
            }

            if ($table->seats()->count() <= 1) {
                $table->delete();

                return;
            }

            $tid = $table->id;
            $seat->delete();

            $remaining = Seat::query()->where('table_id', $tid)->orderBy('seat_index')->get();
            $i = 1;
            foreach ($remaining as $s) {
                $s->update(['seat_index' => $i]);
                $i++;
            }

            $table->update(['capacity' => $remaining->count()]);
        });

        $tableStillExists = Table::query()->where('id', $tableId)->exists();

        return response()->json([
            'ok' => true,
            'removed_table_id' => $tableStillExists ? null : $tableId,
        ]);
    }

    /**
     * Assign selected seats to a new table (group). Seat positions (pos_x/pos_y) are unchanged.
     */
    public function group(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'seat_ids' => ['required', 'array', 'min:1'],
            'seat_ids.*' => ['integer', 'distinct', 'exists:seats,id'],
            'label' => ['nullable', 'string', 'max:50'],
        ]);

        $seatIds = array_values(array_unique($validated['seat_ids']));

        $result = DB::transaction(function () use ($seatIds, $validated) {
            $seats = Seat::query()
                ->whereIn('id', $seatIds)
                ->lockForUpdate()
                ->get();

            if ($seats->count() !== count($seatIds)) {
                throw ValidationException::withMessages([
                    'seat_ids' => ['Some seats were not found.'],
                ]);
            }

            $oldTableIds = $seats->pluck('table_id')->unique()->values()->all();

            $label = isset($validated['label']) && $validated['label'] !== ''
                ? trim($validated['label'])
                : $this->nextDefaultTableLabel();

            // Guest capacity: sum each merged table's capacity once (not "number of dots").
            // Floor at dot count so capacity is never below physical markers on the map.
            $sumMergedGuestCap = (int) Table::query()
                ->whereIn('id', $oldTableIds)
                ->sum('capacity');
            $mergedCapacity = max(count($seatIds), $sumMergedGuestCap);

            $table = Table::query()->create([
                'venue_id' => 1,
                'label' => $label,
                'capacity' => $mergedCapacity,
                'status' => 'available',
                'shape' => 'rect',
                'furniture_type' => 'standard',
            ]);

            $index = 1;
            foreach ($seatIds as $sid) {
                $seat = $seats->firstWhere('id', $sid);
                $seat->update([
                    'table_id' => $table->id,
                    'seat_index' => $index,
                ]);
                $index++;
            }

            foreach ($oldTableIds as $oldId) {
                if ((int) $oldId === (int) $table->id) {
                    continue;
                }
                $old = Table::query()->find($oldId);
                if (! $old) {
                    continue;
                }
                if ($old->seats()->count() === 0 && $old->bookings()->count() === 0) {
                    $old->delete();
                }
            }

            return [
                'table_id' => $table->id,
                'table_label' => $table->label,
            ];
        });

        return response()->json([
            'ok' => true,
            'table' => $result,
        ]);
    }

    /**
     * Split a grouped table back into one table per seat marker.
     *
     * Original table rows are not retained during merge, so unmerge creates fresh table rows,
     * preserves each marker's position/status, and keeps the merged guest capacity total.
     */
    public function unmerge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'seat_id' => ['required', 'integer', 'exists:seats,id'],
        ]);

        $seat = Seat::query()->findOrFail($validated['seat_id']);
        $table = Table::query()->findOrFail($seat->table_id);

        if ($table->bookings()->exists() || $table->booking_id !== null) {
            throw ValidationException::withMessages([
                'seat_id' => ['This table has bookings and cannot be unmerged.'],
            ]);
        }

        $result = DB::transaction(function () use ($table) {
            $lockedTable = Table::query()
                ->lockForUpdate()
                ->findOrFail($table->id);

            $seats = Seat::query()
                ->where('table_id', $lockedTable->id)
                ->orderBy('seat_index')
                ->lockForUpdate()
                ->get();

            if ($seats->count() <= 1) {
                throw ValidationException::withMessages([
                    'seat_id' => ['This table is not merged.'],
                ]);
            }

            $seatCount = $seats->count();
            $totalCapacity = max($seatCount, (int) $lockedTable->capacity);
            $baseCapacity = intdiv($totalCapacity, $seatCount);
            $remainder = $totalCapacity % $seatCount;
            $newTables = [];

            foreach ($seats->values() as $index => $seat) {
                $capacity = $baseCapacity + ($index < $remainder ? 1 : 0);
                $status = $this->seatStatusToTableStatus((string) $seat->status);

                $newTable = Table::query()->create([
                    'venue_id' => $lockedTable->venue_id,
                    'label' => $this->unmergedTableLabel($lockedTable->label, $index + 1),
                    'capacity' => $capacity,
                    'status' => $status,
                    'is_accessible' => $lockedTable->is_accessible,
                    'accessible_features' => $lockedTable->accessible_features,
                    'shape' => $lockedTable->shape,
                    'furniture_type' => $lockedTable->furniture_type ?? 'standard',
                ]);

                $seat->update([
                    'table_id' => $newTable->id,
                    'seat_index' => 1,
                ]);

                $newTables[] = [
                    'id' => $newTable->id,
                    'label' => $newTable->label,
                    'capacity' => (int) $newTable->capacity,
                ];
            }

            $lockedTable->delete();

            return [
                'removed_table_id' => $lockedTable->id,
                'tables' => $newTables,
            ];
        });

        return response()->json([
            'ok' => true,
            'removed_table_id' => $result['removed_table_id'],
            'tables' => $result['tables'],
        ]);
    }

    private function nextDefaultTableLabel(): string
    {
        $max = 0;
        foreach (Table::query()->pluck('label') as $l) {
            if (preg_match('/^T(\d+)$/i', (string) $l, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return 'T'.($max + 1);
    }

    private function assertTableLabelAvailable(string $label, ?int $exceptTableId = null): void
    {
        if ($label === '') {
            throw ValidationException::withMessages([
                'label' => ['Enter a table name.'],
            ]);
        }

        $query = Table::query()
            ->whereRaw('LOWER(label) = ?', [mb_strtolower($label)]);

        if ($exceptTableId !== null) {
            $query->whereKeyNot($exceptTableId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'label' => ["Table name {$label} is already in use."],
            ]);
        }
    }

    private function assertMergeGroupsArePhysicallyValid(array $groups): void
    {
        $ids = collect($groups)
            ->flatMap(fn (array $group) => $group['table_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $tables = Table::query()
            ->with('seats')
            ->whereIn('id', $ids->all())
            ->get()
            ->keyBy('id');

        foreach ($groups as $group) {
            if (! $this->mergeGroupIsConnected($group['table_ids'] ?? [], $tables)) {
                throw ValidationException::withMessages([
                    'groups' => ['These tables are too far apart to merge.'],
                ]);
            }
        }
    }

    private function mergeGroupIsConnected(array $ids, $tables): bool
    {
        $ids = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (count($ids) < 2) {
            return false;
        }

        $first = $ids[0];
        if (! $tables->has($first)) {
            return false;
        }

        $seen = [$first => true];
        $queue = [$first];

        while ($queue !== []) {
            $currentId = array_shift($queue);
            $current = $tables->get($currentId);

            foreach ($ids as $candidateId) {
                if (isset($seen[$candidateId]) || ! $tables->has($candidateId)) {
                    continue;
                }

                if (! $this->tablesCanMergePhysically($current, $tables->get($candidateId))) {
                    continue;
                }

                $seen[$candidateId] = true;
                $queue[] = $candidateId;
            }
        }

        return count($seen) === count($ids);
    }

    private function tablesCanMergePhysically(Table $a, Table $b): bool
    {
        if ($this->tableMergeGroup($a) !== $this->tableMergeGroup($b)) {
            return false;
        }

        $pointA = $this->tableMergePoint($a);
        $pointB = $this->tableMergePoint($b);
        if ($pointA === null || $pointB === null) {
            return false;
        }

        return hypot($pointA['x'] - $pointB['x'], $pointA['y'] - $pointB['y']) <= self::MERGE_DISTANCE_LIMIT;
    }

    private function tableMergeGroup(Table $table): string
    {
        return (string) ($table->getAttribute('merge_group') ?? 'default');
    }

    /**
     * @return array{x: float, y: float}|null
     */
    private function tableMergePoint(Table $table): ?array
    {
        $seats = $table->relationLoaded('seats') ? $table->seats : $table->seats()->get();

        if ($seats->isNotEmpty()) {
            return [
                'x' => (float) $seats->avg('pos_x'),
                'y' => (float) $seats->avg('pos_y'),
            ];
        }

        if ($table->position_x === null || $table->position_y === null) {
            return null;
        }

        $x = (float) $table->position_x;
        $y = (float) $table->position_y;

        if ($x > 100 || $y > 100) {
            return [
                'x' => $this->plannerPxToSeatPercent($x, SeatingLayoutController::PLANNER_WIDTH),
                'y' => $this->plannerPxToSeatPercent($y, SeatingLayoutController::PLANNER_HEIGHT),
            ];
        }

        return ['x' => $x, 'y' => $y];
    }

    private function legacyShape(string $plannerShape): string
    {
        return $plannerShape === 'round' ? 'circle' : 'rect';
    }

    private function furnitureTypeForPlannerShape(string $plannerShape): string
    {
        return match ($plannerShape) {
            'counter' => 'bar_stool',
            'booth' => 'booth',
            default => 'standard',
        };
    }

    private function plannerPxToSeatPercent(float $value, int $max): float
    {
        return round(max(0, min(100, ($value / $max) * 100)), 4);
    }

    private function tableHasActiveBooking(Table $table): bool
    {
        if ($table->booking_id !== null) {
            return true;
        }

        return $table->bookings()
            ->whereIn('status', ['active', 'pending'])
            ->exists();
    }

    private function seatStatusToTableStatus(string $seatStatus): string
    {
        return match ($seatStatus) {
            'reserved' => 'reserved',
            'occupied' => 'occupied',
            'cleaning' => 'cleaning',
            default => 'available',
        };
    }

    private function unmergedTableLabel(string $label, int $index): string
    {
        $suffix = '-'.$index;

        return mb_substr($label, 0, 50 - mb_strlen($suffix)).$suffix;
    }
}
