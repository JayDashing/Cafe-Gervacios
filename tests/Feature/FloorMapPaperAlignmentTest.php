<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Seat;
use App\Models\Table;
use App\Models\User;
use App\Livewire\Admin\DashboardSeatMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FloorMapPaperAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_floor_map_loads_existing_layout_data(): void
    {
        [$table, $seat] = $this->tableWithSeats('A1', 2, [[20, 30]]);

        $this->actingAs($this->admin())
            ->getJson(route('admin.api.seats'))
            ->assertOk()
            ->assertJsonPath('seats.0.id', $seat->id)
            ->assertJsonPath('seats.0.table_id', $table->id)
            ->assertJsonPath('seats.0.table_label', 'A1')
            ->assertJsonPath('seats.0.table_capacity', 2);
    }

    public function test_floor_map_editor_exposes_blueprint_image_boundary(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.tables', ['edit' => 1]))
            ->assertOk()
            ->assertSeeHtml('data-blueprint-image');
    }

    public function test_floor_map_daily_ui_hides_table_list_tab(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.tables'))
            ->assertOk()
            ->assertSee('Floor Map')
            ->assertSee('Calendar')
            ->assertSee('Edit Layout')
            ->assertSee(route('admin.tables', ['edit' => 1]), false)
            ->assertSeeHtml('data-blueprint-floor-map')
            ->assertSeeHtml('data-operations-mode="false"')
            ->assertDontSee('Table List');
    }

    public function test_current_floor_map_editor_exposes_grouping_and_delete_apis(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.tables', ['edit' => 1]))
            ->assertOk()
            ->assertSee('Add Table Marker')
            ->assertSee('Merge Tables')
            ->assertSeeHtml('data-api-group="'.route('admin.api.seats.group').'"')
            ->assertSeeHtml('data-api-delete="'.route('admin.api.seats.delete').'"');
    }

    public function test_admin_can_place_table_and_invalid_coordinates_are_rejected(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('admin.api.seats.place'), [
                'pos_x' => 25,
                'pos_y' => 40,
                'label' => 'P1',
                'capacity' => 4,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('seat.table_label', 'P1');

        $this->assertDatabaseHas('tables', ['label' => 'P1', 'capacity' => 4]);
        $this->assertDatabaseHas('seats', ['pos_x' => 25, 'pos_y' => 40]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.seats.place'), [
                'pos_x' => 96,
                'pos_y' => 40,
                'label' => 'P2',
                'image_width' => 1000,
                'image_height' => 600,
                'marker_width' => 80,
                'marker_height' => 44,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('tables', ['label' => 'P2']);
        $this->assertDatabaseHas('seats', ['pos_x' => 96, 'pos_y' => 40]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.seats.place'), [
                'pos_x' => 120,
                'pos_y' => 40,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pos_x']);

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.seats.place'), [
                'pos_x' => 99,
                'pos_y' => 40,
                'image_width' => 1000,
                'image_height' => 600,
                'marker_width' => 80,
                'marker_height' => 44,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pos_x', 'pos_y'])
            ->assertJsonPath('errors.pos_x.0', 'Table marker must stay inside the blueprint image.');

        $this->assertDatabaseMissing('seats', ['pos_x' => 99, 'pos_y' => 40]);
    }

    public function test_update_table_metadata_and_reject_capacity_below_seat_count(): void
    {
        [$table, $seat] = $this->tableWithSeats('Old', 3, [[10, 10], [12, 10]]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.seats.update'), [
                'seat_id' => $seat->id,
                'label' => 'New',
                'capacity' => 4,
            ])
            ->assertOk()
            ->assertJsonPath('table.label', 'New')
            ->assertJsonPath('table.capacity', 4);

        $this->assertSame('New', $table->refresh()->label);
        $this->assertSame(4, $table->capacity);

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.seats.update'), [
                'seat_id' => $seat->id,
                'pos_x' => 99,
                'pos_y' => 40,
                'image_width' => 1000,
                'image_height' => 600,
                'marker_width' => 80,
                'marker_height' => 44,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pos_x', 'pos_y'])
            ->assertJsonPath('errors.pos_x.0', 'Table marker must stay inside the blueprint image.');

        $seat->refresh();
        $this->assertSame(10.0, $seat->pos_x);
        $this->assertSame(10.0, $seat->pos_y);

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.seats.update'), [
                'seat_id' => $seat->id,
                'capacity' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['capacity']);
    }

    public function test_grouping_seats_creates_one_table_and_preserves_positions(): void
    {
        [, $seatA] = $this->tableWithSeats('G1', 2, [[10, 20]]);
        [, $seatB] = $this->tableWithSeats('G2', 3, [[30, 40]]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.seats.group'), [
                'seat_ids' => [$seatA->id, $seatB->id],
                'label' => 'Merged',
            ])
            ->assertOk()
            ->assertJsonPath('table.table_label', 'Merged');

        $merged = Table::where('label', 'Merged')->firstOrFail();
        $this->assertSame(5, $merged->capacity);
        $this->assertSame($merged->id, $seatA->refresh()->table_id);
        $this->assertSame($merged->id, $seatB->refresh()->table_id);
        $this->assertSame(10.0, $seatA->pos_x);
        $this->assertSame(40.0, $seatB->pos_y);
    }

    public function test_grouped_table_can_be_unmerged_into_individual_tables(): void
    {
        [$tableA, $seatA] = $this->tableWithSeats('U1', 2, [[10, 20]]);
        [$tableB, $seatB] = $this->tableWithSeats('U2', 3, [[30, 40]]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.seats.group'), [
                'seat_ids' => [$seatA->id, $seatB->id],
                'label' => 'Merged',
            ])
            ->assertOk();

        $merged = Table::where('label', 'Merged')->firstOrFail();
        $this->assertDatabaseMissing('tables', ['id' => $tableA->id]);
        $this->assertDatabaseMissing('tables', ['id' => $tableB->id]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.seats.unmerge'), [
                'seat_id' => $seatA->id,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(2, 'tables')
            ->assertJsonPath('removed_table_id', $merged->id);

        $seatA->refresh();
        $seatB->refresh();

        $this->assertNotSame($merged->id, $seatA->table_id);
        $this->assertNotSame($merged->id, $seatB->table_id);
        $this->assertNotSame($seatA->table_id, $seatB->table_id);
        $this->assertSame(1, $seatA->seat_index);
        $this->assertSame(1, $seatB->seat_index);
        $this->assertSame(10.0, $seatA->pos_x);
        $this->assertSame(40.0, $seatB->pos_y);
        $this->assertDatabaseMissing('tables', ['id' => $merged->id]);

        $newTables = Table::whereIn('id', [$seatA->table_id, $seatB->table_id])->get();
        $this->assertSame(2, $newTables->count());
        $this->assertSame(5, (int) $newTables->sum('capacity'));
    }

    public function test_daily_merge_groups_must_use_nearby_tables(): void
    {
        [$nearA] = $this->tableWithSeats('M1', 2, [[10, 10]]);
        [$nearB] = $this->tableWithSeats('M2', 2, [[20, 10]]);
        [$far] = $this->tableWithSeats('M10', 2, [[90, 90]]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.tables.operations.merge-groups'), [
                'groups' => [[
                    'id' => 'near',
                    'table_ids' => [$nearA->id, $nearB->id],
                    'label' => 'M1 + M2',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.tables.operations.merge-groups'), [
                'groups' => [[
                    'id' => 'far',
                    'table_ids' => [$nearA->id, $far->id],
                    'label' => 'M1 + M10',
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['groups']);
    }

    public function test_delete_single_seat_resequences_capacity_and_delete_table_removes_identifier(): void
    {
        [$table, $seat] = $this->tableWithSeats('D1', 3, [[10, 10], [20, 20], [30, 30]]);
        $middleSeat = Seat::where('table_id', $table->id)->where('seat_index', 2)->firstOrFail();

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.seats.delete'), [
                'seat_id' => $middleSeat->id,
                'scope' => 'seat',
            ])
            ->assertOk()
            ->assertJsonPath('removed_table_id', null);

        $this->assertSame([1, 2], Seat::where('table_id', $table->id)->orderBy('seat_index')->pluck('seat_index')->all());
        $this->assertSame(2, $table->refresh()->capacity);

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.seats.delete'), [
                'seat_id' => $seat->id,
                'scope' => 'table',
            ])
            ->assertOk()
            ->assertJsonPath('removed_table_id', $table->id);

        $this->assertDatabaseMissing('tables', ['id' => $table->id]);
    }

    public function test_delete_table_with_existing_bookings_is_blocked(): void
    {
        [$table, $seat] = $this->tableWithSeats('B1', 2, [[10, 10]]);
        Booking::create([
            'booking_ref' => 'GRV-BOOK1',
            'table_id' => $table->id,
            'customer_name' => 'Booked Guest',
            'customer_phone' => '09170000000',
            'party_size' => 2,
            'priority_type' => 'none',
            'status' => 'active',
            'payment_status' => 'paid',
            'booked_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.seats.delete'), [
                'seat_id' => $seat->id,
                'scope' => 'table',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['seat_id']);

        $this->assertDatabaseHas('tables', ['id' => $table->id]);
    }

    public function test_occupied_table_cannot_be_deleted_from_floor_map(): void
    {
        [$table, $seat] = $this->tableWithSeats('O1', 2, [[10, 10]]);
        $table->update(['status' => 'occupied']);

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.seats.delete'), [
                'seat_id' => $seat->id,
                'scope' => 'table',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['seat_id']);

        $this->assertDatabaseHas('tables', ['id' => $table->id]);
        $this->assertDatabaseHas('seats', ['id' => $seat->id]);
    }

    public function test_dashboard_seat_map_click_mode_updates_and_clears_selection(): void
    {
        Livewire::actingAs($this->admin())
            ->test(DashboardSeatMap::class)
            ->assertSee('Edit')
            ->assertSee('Waitlist')
            ->assertSee('Table')
            ->assertSet('seatClickMode', 'edit')
            ->call('setSeatClickMode', 'waitlist')
            ->assertSet('seatClickMode', 'waitlist')
            ->assertDispatched('table-selected', tableId: null)
            ->assertDispatched('table-ops-select', tableId: null)
            ->call('setSeatClickMode', 'table')
            ->assertSet('seatClickMode', 'table');
    }

    public function test_dashboard_seat_map_shows_table_check_status_counts(): void
    {
        $this->tableWithSeats('F1', 2, [[10, 10]]);
        [$reserved] = $this->tableWithSeats('F2', 2, [[20, 20]]);
        $reserved->update(['status' => 'reserved']);
        [$occupied] = $this->tableWithSeats('F3', 2, [[30, 30]]);
        $occupied->update(['status' => 'occupied']);
        Table::create([
            'venue_id' => 1,
            'label' => 'UNPLACED',
            'capacity' => 2,
            'status' => 'cleaning',
        ]);

        Livewire::actingAs($this->admin())
            ->test(DashboardSeatMap::class)
            ->assertSee('Floor Map')
            ->assertSeeHtml('aria-label="Free tables on map: 1"')
            ->assertSeeHtml('aria-label="Reserved tables on map: 1"')
            ->assertSeeHtml('aria-label="Occupied tables on map: 1"')
            ->assertSeeHtml('aria-label="Cleaning tables on map: 0"');
    }

    public function test_operations_status_modal_supports_cleaning_status_for_table_and_seats(): void
    {
        [$table] = $this->tableWithSeats('C1', 2, [[10, 10], [12, 10]]);
        $table->update(['status' => 'occupied']);
        Seat::query()->where('table_id', $table->id)->update(['status' => 'occupied']);

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.tables.operations.status'), [
                'table_id' => $table->id,
                'status' => 'cleaning',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame('cleaning', $table->refresh()->status);
        $this->assertSame(
            ['cleaning', 'cleaning'],
            Seat::query()->where('table_id', $table->id)->orderBy('seat_index')->pluck('status')->all()
        );

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.tables.operations.status'), [
                'table_id' => $table->id,
                'status' => 'available',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame('available', $table->refresh()->status);
        $this->assertSame(
            ['free', 'free'],
            Seat::query()->where('table_id', $table->id)->orderBy('seat_index')->pluck('status')->all()
        );

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.tables.operations.status'), [
                'table_id' => $table->id,
                'status' => 'reserved',
            ])
            ->assertForbidden();

        $this->assertSame('available', $table->refresh()->status);
        $this->assertSame(
            ['free', 'free'],
            Seat::query()->where('table_id', $table->id)->orderBy('seat_index')->pluck('status')->all()
        );

        $this->actingAs($this->admin())
            ->postJson(route('admin.api.tables.operations.status'), [
                'table_id' => $table->id,
                'status' => 'needs_cleaning',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    /**
     * @param  list<array{0: int|float, 1: int|float}>  $positions
     * @return array{0: Table, 1: Seat}
     */
    private function tableWithSeats(string $label, int $capacity, array $positions): array
    {
        $table = Table::create([
            'venue_id' => 1,
            'label' => $label,
            'capacity' => $capacity,
            'status' => 'available',
        ]);

        $first = null;
        foreach ($positions as $index => [$x, $y]) {
            $seat = Seat::create([
                'table_id' => $table->id,
                'seat_index' => $index + 1,
                'status' => 'free',
                'pos_x' => $x,
                'pos_y' => $y,
            ]);
            $first ??= $seat;
        }

        return [$table, $first];
    }
}
