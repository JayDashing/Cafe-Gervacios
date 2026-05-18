<?php

namespace Tests\Feature;

use App\Livewire\StaffWalkInQueue;
use App\Jobs\SendSmsJob;
use App\Models\QueueEntry;
use App\Models\Seat;
use App\Models\Setting;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class StaffWalkInQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_register_walk_in_with_valid_details(): void
    {
        Queue::fake([SendSmsJob::class]);

        $user = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
            'must_change_password' => false,
        ]);
        Table::create([
            'venue_id' => 1,
            'label' => 'T1',
            'capacity' => 4,
            'status' => 'occupied',
            'occupied_at' => now()->subMinutes(20),
            'occupied_party' => 3,
        ]);

        $component = Livewire::actingAs($user)
            ->test(StaffWalkInQueue::class)
            ->set('customer_name', 'Walkin Test')
            ->set('customer_phone', '09171234567')
            ->set('party_size', 3)
            ->set('priority_type', 'none')
            ->call('register')
            ->assertHasNoErrors()
            ->assertDispatched('notify', type: 'success', message: 'Added to queue. Ticket #1')
            ->assertDispatched('walk-in-registration-completed');

        $this->assertSame(1, QueueEntry::count());

        $entry = QueueEntry::first();
        $this->assertSame('Walkin Test', $entry->customer_name);
        $this->assertSame('09171234567', $entry->customer_phone);
        $this->assertSame(3, $entry->party_size);
        $this->assertSame('none', $entry->priority_type);
        $this->assertNotNull($entry->queue_display_number);
        $this->assertGreaterThan(0, $entry->queue_display_number);
        $this->assertNotNull($entry->estimated_wait);
        $this->assertGreaterThan(0, $entry->estimated_wait);
        $component->assertSee('ETA: '.$entry->estimated_wait.' min');
    }

    public function test_pwd_walk_in_displays_estimated_wait_after_registration(): void
    {
        Queue::fake([SendSmsJob::class]);

        $user = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
            'must_change_password' => false,
        ]);
        Table::create([
            'venue_id' => 1,
            'label' => 'T1',
            'capacity' => 4,
            'status' => 'occupied',
            'occupied_at' => now()->subMinutes(15),
            'occupied_party' => 2,
        ]);

        $component = Livewire::actingAs($user)
            ->test(StaffWalkInQueue::class)
            ->set('customer_name', 'Pwd Guest')
            ->set('customer_phone', '09171234568')
            ->set('party_size', 2)
            ->set('priority_type', 'pwd')
            ->call('register')
            ->assertHasNoErrors()
            ->assertDispatched('notify', type: 'success', message: 'Added to queue. Ticket #1')
            ->assertDispatched('walk-in-registration-completed');

        $entry = QueueEntry::firstOrFail();
        $this->assertSame('pwd', $entry->priority_type);
        $this->assertNotNull($entry->estimated_wait);
        $this->assertGreaterThan(0, $entry->estimated_wait);
        $component->assertSee('ETA: '.$entry->estimated_wait.' min');
    }

    public function test_staff_can_select_compatible_floor_map_table_and_still_add_to_queue(): void
    {
        $user = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $table = $this->tableWithSeat('T4', 4, 'available', 40, 45);

        Livewire::actingAs($user)
            ->test(StaffWalkInQueue::class)
            ->set('party_size', 2)
            ->call('selectTable', $table->id)
            ->assertSet('selectedTableId', $table->id)
            ->assertDispatched('notify', type: 'success', message: 'Table T4 selected.')
            ->assertSee('Selected Table: T4')
            ->set('customer_name', 'Marker Guest')
            ->set('customer_phone', '')
            ->set('priority_type', 'none')
            ->call('register')
            ->assertHasNoErrors()
            ->assertSet('selectedTableId', null)
            ->assertDispatched('notify', type: 'success', message: 'Added to queue. Ticket #1')
            ->assertDispatched('walk-in-registration-completed');

        $entry = QueueEntry::firstOrFail();
        $this->assertSame('waiting', $entry->status);
        $this->assertSame('Marker Guest', $entry->customer_name);
    }

    public function test_guided_walk_in_action_adds_to_waitlist_when_no_suitable_table_exists(): void
    {
        Queue::fake([SendSmsJob::class]);

        $user = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $this->tableWithSeat('T2', 2, 'occupied', 30, 35);

        Livewire::actingAs($user)
            ->test(StaffWalkInQueue::class)
            ->set('customer_name', 'Waitlist Guided Guest')
            ->set('customer_phone', '')
            ->set('party_size', 2)
            ->set('priority_type', 'none')
            ->assertSee('No suitable table available. Guest will be added to the waitlist.')
            ->assertSee('Add to Waitlist')
            ->call('submitGuidedAction')
            ->assertHasNoErrors()
            ->assertDispatched('notify', type: 'success', message: 'Added to queue. Ticket #1');

        $this->assertSame('waiting', QueueEntry::firstOrFail()->status);
    }

    public function test_guided_walk_in_action_requires_table_selection_when_suitable_table_exists(): void
    {
        $user = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $table = $this->tableWithSeat('T4', 4, 'available', 40, 45);

        Livewire::actingAs($user)
            ->test(StaffWalkInQueue::class)
            ->set('customer_name', 'Seat Guided Guest')
            ->set('customer_phone', '')
            ->set('party_size', 2)
            ->set('priority_type', 'none')
            ->assertSee('Select an available table to seat guest.')
            ->assertSee('Seat Guest')
            ->assertDontSee('Add to Waitlist')
            ->call('submitGuidedAction')
            ->assertDispatched('notify', type: 'error', message: 'Select an available table to seat guest.')
            ->call('selectTable', $table->id)
            ->assertSee('Seat Guest at T4')
            ->call('submitGuidedAction')
            ->assertHasNoErrors()
            ->assertDispatched('notify', type: 'success', message: 'Guest seated at table T4.');

        $this->assertSame('seated', QueueEntry::firstOrFail()->status);
        $this->assertSame('occupied', $table->refresh()->status);
    }

    public function test_incompatible_floor_map_marker_selection_shows_error(): void
    {
        $user = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $table = $this->tableWithSeat('T2', 1, 'available', 30, 35);

        Livewire::actingAs($user)
            ->test(StaffWalkInQueue::class)
            ->set('party_size', 2)
            ->call('selectTable', $table->id)
            ->assertSet('selectedTableId', null)
            ->assertDispatched('notify', type: 'error', message: 'This table is not available for the selected party size.');
    }

    public function test_staff_can_seat_walk_in_at_selected_floor_map_table(): void
    {
        $user = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $table = $this->tableWithSeat('T1', 4, 'available', 25, 30);

        Livewire::actingAs($user)
            ->test(StaffWalkInQueue::class)
            ->set('customer_name', 'Seat Now Guest')
            ->set('customer_phone', '')
            ->set('party_size', 2)
            ->set('priority_type', 'none')
            ->call('selectTable', $table->id)
            ->call('seatSelectedTable')
            ->assertHasNoErrors()
            ->assertSet('selectedTableId', null)
            ->assertDispatched('notify', type: 'success', message: 'Guest seated at table T1.')
            ->assertDispatched('walk-in-registration-completed');

        $entry = QueueEntry::firstOrFail();
        $this->assertSame('seated', $entry->status);
        $this->assertSame('occupied', $table->refresh()->status);
        $this->assertSame(2, (int) $table->occupied_party);
    }

    public function test_pwd_accessible_rule_disables_inaccessible_floor_map_tables(): void
    {
        Setting::set('queue_pwd_requires_accessible_table', '1');

        $user = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $inaccessibleTable = $this->tableWithSeat('T2', 4, 'available', 30, 35);
        $accessibleTable = $this->tableWithSeat('T3', 4, 'available', 45, 35, true);

        Livewire::actingAs($user)
            ->test(StaffWalkInQueue::class)
            ->set('party_size', 2)
            ->set('priority_type', 'pwd')
            ->call('selectTable', $inaccessibleTable->id)
            ->assertSet('selectedTableId', null)
            ->assertDispatched('notify', type: 'error', message: 'This table is not available for the selected party size.')
            ->call('selectTable', $accessibleTable->id)
            ->assertSet('selectedTableId', $accessibleTable->id)
            ->assertDispatched('notify', type: 'success', message: 'Table T3 selected.');
    }

    private function tableWithSeat(string $label, int $capacity, string $status, float $x, float $y, bool $isAccessible = false): Table
    {
        $table = Table::create([
            'venue_id' => 1,
            'label' => $label,
            'capacity' => $capacity,
            'status' => $status,
            'is_accessible' => $isAccessible,
        ]);

        Seat::create([
            'table_id' => $table->id,
            'seat_index' => 1,
            'status' => $status === 'available' ? 'free' : $status,
            'pos_x' => $x,
            'pos_y' => $y,
        ]);

        return $table;
    }
}
