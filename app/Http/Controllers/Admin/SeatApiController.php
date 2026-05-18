<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Seat;
use App\Models\Table;
use App\Services\TableService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SeatApiController extends Controller
{
    private const MERGE_DISTANCE_LIMIT = 18.0;
    private const FLOOR_MAP_BOUNDARY_MESSAGE = 'Table marker must stay inside the blueprint image.';

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
                $seatCount = $this->minimumCapacityFor($table);
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
        if ((string) $request->input('status') === Table::STATUS_RESERVED) {
            abort(403, 'Staff cannot mark tables reserved from the floor map.');
        }

        $validated = $request->validate([
            'table_id' => ['required', 'integer', 'exists:tables,id'],
            'status' => ['nullable', 'required_without:action', 'string', 'in:'.implode(',', [
                Table::STATUS_AVAILABLE,
                Table::STATUS_OCCUPIED,
                Table::STATUS_CLEANING,
            ])],
            'action' => ['nullable', 'required_without:status', 'string', 'in:'.implode(',', [
                'check_in',
                'no_show',
                'seat_walk_in',
                'send_to_cleaning',
                'mark_free',
                'release_table',
            ])],
        ]);

        $table = Table::query()->findOrFail((int) $validated['table_id']);
        Gate::authorize('update', $table);

        try {
            if (isset($validated['action']) && $validated['action'] !== null && $validated['action'] !== '') {
                $this->applyPlannerAction($table, (string) $validated['action']);
            } else {
                $this->applyPlannerStatus($table, (string) $validated['status']);
            }
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'status' => [$e->getMessage()],
            ]);
        } catch (QueryException $e) {
            report($e);

            throw ValidationException::withMessages([
                'status' => ['Invalid table status. Please refresh and try again.'],
            ]);
        }

        Cache::forget('tables.venue.1');

        return response()->json([
            'ok' => true,
            'planner' => SeatingLayoutController::plannerData(),
        ]);
    }

    private function applyPlannerAction(Table $table, string $action): void
    {
        $service = app(TableService::class);

        match ($action) {
            'check_in' => $service->checkIn($table->id),
            'no_show' => $service->noShow($table->id),
            'seat_walk_in' => $service->seatWalkIn($table->id),
            'send_to_cleaning' => $service->sendToCleaning($table->id),
            'mark_free' => $service->markFree($table->id),
            'release_table' => $service->releaseTable($table->id),
            default => throw new \InvalidArgumentException('Invalid table action.'),
        };
    }

    private function applyPlannerStatus(Table $table, string $status): void
    {
        if ($table->status === $status) {
            return;
        }

        $service = app(TableService::class);

        match ($status) {
            Table::STATUS_OCCUPIED => $table->status === Table::STATUS_RESERVED && $table->booking_id !== null
                ? $service->checkIn($table->id)
                : $service->seatWalkIn($table->id),
            Table::STATUS_CLEANING => $service->sendToCleaning($table->id),
            Table::STATUS_AVAILABLE => $table->status === Table::STATUS_RESERVED
                ? $service->releaseTable($table->id)
                : $service->markFree($table->id),
            default => throw new \InvalidArgumentException('Invalid table status.'),
        };
    }

    public function plannerDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table_id' => ['required', 'integer', 'exists:tables,id'],
        ]);

        $table = Table::query()->findOrFail((int) $validated['table_id']);

        if ($this->tableCannotBeRemoved($table)) {
            throw ValidationException::withMessages([
                'table_id' => ['This table has bookings or is currently in use and cannot be deleted.'],
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
        if ((string) $request->input('status') === Seat::STATUS_RESERVED) {
            abort(403, 'Staff cannot mark tables reserved from the floor map.');
        }

        $validated = $request->validate([
            'pos_x' => ['required', 'numeric', 'min:0', 'max:100'],
            'pos_y' => ['required', 'numeric', 'min:0', 'max:100'],
            'image_width' => ['nullable', 'numeric', 'gt:0', 'max:20000'],
            'image_height' => ['nullable', 'numeric', 'gt:0', 'max:20000'],
            'container_width' => ['nullable', 'numeric', 'gt:0', 'max:20000'],
            'container_height' => ['nullable', 'numeric', 'gt:0', 'max:20000'],
            'marker_width' => ['nullable', 'numeric', 'gt:0', 'max:2000'],
            'marker_height' => ['nullable', 'numeric', 'gt:0', 'max:2000'],
            'table_id' => ['nullable', 'integer', 'exists:tables,id'],
            'label' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'furniture_type' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', 'string', 'in:'.implode(',', Seat::STATUSES)],
        ]);
        $this->assertMarkerInsideBlueprint($validated);

        $result = DB::transaction(function () use ($validated) {
            $restoreId = (int) ($validated['table_id'] ?? 0);
            $table = null;

            if ($restoreId > 0) {
                $table = Table::query()->lockForUpdate()->findOrFail($restoreId);
                if ($table->seats()->exists()) {
                    throw ValidationException::withMessages([
                        'table_id' => ['This table is already on the floor map.'],
                    ]);
                }
            }

            $label = isset($validated['label']) && trim((string) $validated['label']) !== ''
                ? trim((string) $validated['label'])
                : $this->nextDefaultTableLabel();
            if (! $table) {
                $this->assertTableLabelAvailable($label);
            }

            $furniture = trim((string) ($validated['furniture_type'] ?? 'standard'));
            if ($furniture === '') {
                $furniture = 'standard';
            }

            $capacity = $table ? (int) $table->capacity : (int) ($validated['capacity'] ?? 1);
            $seatStatus = isset($validated['status']) && $validated['status'] !== ''
                ? $validated['status']
                : Seat::STATUS_FREE;

            if ($table) {
                $table->update([
                    'status' => $this->seatStatusToTableStatus($seatStatus),
                ]);
            } else {
                $table = Table::query()->create([
                    'venue_id' => 1,
                    'label' => $label,
                    'capacity' => $capacity,
                    'status' => $this->seatStatusToTableStatus($seatStatus),
                    'shape' => 'rect',
                    'furniture_type' => $furniture,
                ]);
            }

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
                'capacity' => (int) $table->capacity,
                'furniture_type' => (string) ($table->furniture_type ?? 'standard'),
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
        if ((string) $request->input('status') === Seat::STATUS_RESERVED) {
            abort(403, 'Staff cannot mark tables reserved from the floor map.');
        }

        $validated = $request->validate([
            'seat_id' => ['required', 'integer', 'exists:seats,id'],
            'status' => ['nullable', 'string', 'in:'.implode(',', Seat::STATUSES)],
            'label' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'furniture_type' => ['nullable', 'string', 'max:32'],
            'pos_x' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pos_y' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'image_width' => ['nullable', 'numeric', 'gt:0', 'max:20000'],
            'image_height' => ['nullable', 'numeric', 'gt:0', 'max:20000'],
            'container_width' => ['nullable', 'numeric', 'gt:0', 'max:20000'],
            'container_height' => ['nullable', 'numeric', 'gt:0', 'max:20000'],
            'marker_width' => ['nullable', 'numeric', 'gt:0', 'max:2000'],
            'marker_height' => ['nullable', 'numeric', 'gt:0', 'max:2000'],
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
        if ($hasPosition) {
            $this->assertMarkerInsideBlueprint($validated);
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
                $seatCount = $this->minimumCapacityFor($table);
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
                'min_capacity' => $this->minimumCapacityFor($table),
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

        if ($this->tableCannotBeRemoved($table)) {
            throw ValidationException::withMessages([
                'seat_id' => ['This table has bookings or is currently in use and cannot be removed.'],
            ]);
        }

        $scope = $validated['scope'];
        $tableId = $table->id;

        DB::transaction(function () use ($seat, $table, $scope) {
            if ($scope === 'table') {
                $table->seats()->delete();

                return;
            }

            $seatCount = $table->seats()->count();
            if ($seatCount <= 1) {
                $capacity = (int) $table->capacity;
                if ($capacity > 1) {
                    $table->update(['capacity' => $capacity - 1]);

                    return;
                }

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

            $table->update([
                'capacity' => max($remaining->count(), ((int) $table->capacity) - 1),
            ]);
        });

        $tableStillExists = Table::query()->where('id', $tableId)->exists();

        return response()->json([
            'ok' => true,
            'removed_table_id' => $scope === 'table' || ! $tableStillExists ? $tableId : null,
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

    private function assertMarkerInsideBlueprint(array $data): void
    {
        $dimensionKeys = ['image_width', 'image_height', 'marker_width', 'marker_height'];
        $fallbackDimensionKeys = ['container_width', 'container_height', 'marker_width', 'marker_height'];

        $hasImageDimensions = collect($dimensionKeys)
            ->contains(fn (string $key) => array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '');
        $hasFallbackDimensions = collect($fallbackDimensionKeys)
            ->contains(fn (string $key) => array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '');

        if (! $hasImageDimensions && ! $hasFallbackDimensions) {
            return;
        }

        $widthKey = $hasImageDimensions ? 'image_width' : 'container_width';
        $heightKey = $hasImageDimensions ? 'image_height' : 'container_height';
        $requiredKeys = [$widthKey, $heightKey, 'marker_width', 'marker_height'];

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
                throw ValidationException::withMessages([
                    'pos_x' => [self::FLOOR_MAP_BOUNDARY_MESSAGE],
                    'pos_y' => [self::FLOOR_MAP_BOUNDARY_MESSAGE],
                ]);
            }
        }

        $containerWidth = (float) $data[$widthKey];
        $containerHeight = (float) $data[$heightKey];
        $markerWidth = (float) $data['marker_width'];
        $markerHeight = (float) $data['marker_height'];

        if ($containerWidth <= 0 || $containerHeight <= 0 || $markerWidth <= 0 || $markerHeight <= 0) {
            throw ValidationException::withMessages([
                'pos_x' => [self::FLOOR_MAP_BOUNDARY_MESSAGE],
                'pos_y' => [self::FLOOR_MAP_BOUNDARY_MESSAGE],
            ]);
        }

        $centerX = ((float) $data['pos_x'] / 100) * $containerWidth;
        $centerY = ((float) $data['pos_y'] / 100) * $containerHeight;
        $halfWidth = $markerWidth / 2;
        $halfHeight = $markerHeight / 2;
        $tolerance = 0.5;

        if (
            $centerX - $halfWidth < -$tolerance
            || $centerY - $halfHeight < -$tolerance
            || $centerX + $halfWidth > $containerWidth + $tolerance
            || $centerY + $halfHeight > $containerHeight + $tolerance
        ) {
            throw ValidationException::withMessages([
                'pos_x' => [self::FLOOR_MAP_BOUNDARY_MESSAGE],
                'pos_y' => [self::FLOOR_MAP_BOUNDARY_MESSAGE],
            ]);
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

    private function tableCannotBeRemoved(Table $table): bool
    {
        if (in_array($table->status, [Table::STATUS_RESERVED, Table::STATUS_OCCUPIED], true)) {
            return true;
        }

        if ($this->tableHasActiveBooking($table)) {
            return true;
        }

        return $table->bookings()->exists();
    }

    private function minimumCapacityFor(Table $table): int
    {
        $mappedSeats = $table->relationLoaded('seats')
            ? $table->seats->count()
            : $table->seats()->count();

        return max(1, $mappedSeats, (int) $table->capacity);
    }

    private function seatStatusToTableStatus(string $seatStatus): string
    {
        return match ($seatStatus) {
            Seat::STATUS_RESERVED => Table::STATUS_RESERVED,
            Seat::STATUS_OCCUPIED => Table::STATUS_OCCUPIED,
            Seat::STATUS_CLEANING => Table::STATUS_CLEANING,
            default => Table::STATUS_AVAILABLE,
        };
    }

    private function unmergedTableLabel(string $label, int $index): string
    {
        $suffix = '-'.$index;

        return mb_substr($label, 0, 50 - mb_strlen($suffix)).$suffix;
    }
}
