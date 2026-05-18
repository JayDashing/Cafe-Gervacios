<?php

namespace Tests\Feature;

use App\Jobs\SendSmsJob;
use App\Livewire\Admin\WaitlistPanel;
use App\Livewire\StaffWalkInQueue;
use App\Models\QueueEntry;
use App\Models\Seat;
use App\Models\Setting;
use App\Models\Table;
use App\Models\User;
use App\Services\QueueService;
use App\Services\TableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class WaitlistPaperAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_walk_in_form_blocks_missing_required_fields(): void
    {
        Livewire::actingAs($this->user('staff'))
            ->test(StaffWalkInQueue::class)
            ->set('customer_name', '')
            ->set('customer_phone', '09171234567')
            ->set('party_size', '')
            ->call('register')
            ->assertHasErrors(['customer_name', 'party_size']);

        $this->assertSame(0, QueueEntry::count());
    }

    public function test_walk_in_form_blocks_duplicate_active_phone(): void
    {
        QueueEntry::create([
            'customer_name' => 'Existing Guest',
            'customer_phone' => '09171234567',
            'party_size' => 2,
            'priority_type' => 'none',
            'priority_score' => 0,
            'needs_accessible' => false,
            'status' => 'waiting',
            'joined_at' => now(),
        ]);

        Livewire::actingAs($this->user('staff'))
            ->test(StaffWalkInQueue::class)
            ->set('customer_name', 'Duplicate Guest')
            ->set('customer_phone', '09171234567')
            ->set('party_size', 2)
            ->call('register')
            ->assertDispatched('notify', type: 'error', message: 'This phone already has an active booking or queue entry.');

        $this->assertSame(1, QueueEntry::count());
    }

    public function test_walk_in_registration_allows_guest_without_phone(): void
    {
        Livewire::actingAs($this->user('staff'))
            ->test(StaffWalkInQueue::class)
            ->set('customer_name', 'No Phone Guest')
            ->set('customer_phone', '')
            ->set('party_size', 2)
            ->call('register')
            ->assertHasNoErrors()
            ->assertDispatched('notify', type: 'success', message: 'Added to queue. Ticket #1');

        $entry = QueueEntry::firstOrFail();
        $this->assertSame('No Phone Guest', $entry->customer_name);
        $this->assertSame('', $entry->customer_phone);
        $this->assertSame(1, $entry->queue_display_number);
    }

    public function test_manual_table_ready_sms_sets_hold_code_and_reserved_table(): void
    {
        Queue::fake([SendSmsJob::class]);

        $entry = $this->queueEntry();
        $table = $this->table(capacity: 4, status: 'available');

        Livewire::actingAs($this->user('admin'))
            ->test(WaitlistPanel::class)
            ->call('sendSmsManually', $entry->id)
            ->assertDispatched('notify', type: 'success', message: 'SMS sent.');

        $entry->refresh();
        $table->refresh();

        $this->assertSame('notified', $entry->status);
        $this->assertNotNull($entry->hold_expires_at);
        $this->assertNotEmpty($entry->hold_confirmation_code);
        $this->assertSame($table->id, $entry->reserved_table_id);
        $this->assertSame('reserved', $table->status);
        Queue::assertPushed(SendSmsJob::class, 1);
    }

    public function test_notified_guest_requires_correct_confirmation_code_before_seating(): void
    {
        Queue::fake([SendSmsJob::class]);

        $entry = $this->queueEntry();
        $table = $this->table(capacity: 4, status: 'available');
        app(\App\Services\QueueService::class)->notifyEntryManually($entry->id);
        $entry->refresh();

        Livewire::actingAs($this->user('admin'))
            ->test(WaitlistPanel::class)
            ->set('holdCode.'.$entry->id, 'WRONG1')
            ->call('confirmAndSeat', $entry->id, $table->id)
            ->assertHasErrors(['holdCode.'.$entry->id])
            ->assertDispatched('notify', type: 'error', message: 'Confirmation code does not match.');

        $this->assertSame('notified', $entry->refresh()->status);
        $this->assertSame('reserved', $table->refresh()->status);

        Livewire::actingAs($this->user('admin'))
            ->test(WaitlistPanel::class)
            ->set('holdCode.'.$entry->id, $entry->hold_confirmation_code)
            ->call('confirmAndSeat', $entry->id, $table->id)
            ->assertHasNoErrors()
            ->assertDispatched('notify', type: 'success', message: 'Guest seated.');

        $this->assertSame('seated', $entry->refresh()->status);
        $this->assertSame('occupied', $table->refresh()->status);
    }

    public function test_cancel_waitlist_entry_releases_held_table(): void
    {
        $table = $this->table(capacity: 4, status: 'reserved');
        $entry = $this->queueEntry([
            'status' => 'notified',
            'reserved_table_id' => $table->id,
            'hold_expires_at' => now()->addMinutes(5),
            'hold_confirmation_code' => 'ABC123',
        ]);

        Livewire::actingAs($this->user('admin'))
            ->test(WaitlistPanel::class)
            ->call('cancelEntry', $entry->id)
            ->assertDispatched('notify', type: 'info', message: 'Removed from queue.');

        $this->assertSame('cancelled', $entry->refresh()->status);
        $this->assertNull($entry->reserved_table_id);
        $this->assertSame('available', $table->refresh()->status);
    }

    public function test_extend_notified_hold_adds_five_minutes(): void
    {
        $entry = $this->queueEntry([
            'status' => 'notified',
            'hold_expires_at' => now()->addMinutes(10),
        ]);
        $before = $entry->hold_expires_at->copy();

        Livewire::actingAs($this->user('admin'))
            ->test(WaitlistPanel::class)
            ->call('extendHold', $entry->id)
            ->assertDispatched('notify', type: 'success', message: 'Hold extended by 5 minutes.');

        $this->assertTrue($entry->refresh()->hold_expires_at->equalTo($before->addMinutes(5)));
    }

    public function test_waitlist_card_more_button_opens_guest_details_and_eta(): void
    {
        $entry = $this->queueEntry([
            'customer_name' => 'PWD Detail Guest',
            'customer_phone' => '09171234569',
            'priority_type' => 'pwd',
            'priority_score' => 100,
            'estimated_wait' => 18,
            'last_estimated_wait' => 18,
            'queue_display_number' => 7,
        ]);

        Livewire::actingAs($this->user('admin'))
            ->test(WaitlistPanel::class)
            ->assertSee('More')
            ->assertSee('PWD Detail Guest')
            ->assertSee('Queue #'.$entry->queue_display_number)
            ->assertSee('ETA: 18 min')
            ->assertSee('Phone')
            ->assertSee('Queue actions')
            ->assertSeeHtml('aria-haspopup="dialog"')
            ->assertSeeHtml('x-on:click.stop="detailsOpen = true"');
    }

    public function test_waitlist_page_opens_walk_in_registration_as_modal(): void
    {
        Livewire::actingAs($this->user('admin'))
            ->test(WaitlistPanel::class)
            ->assertSee('Add Walk-in')
            ->assertDontSee('Register Walk-in')
            ->call('openWalkInModal')
            ->assertSet('showWalkInModal', true)
            ->assertSee('Register Walk-in')
            ->assertSee('Selected Table')
            ->assertSee('Floor Map')
            ->assertSee('No suitable table available. Guest will be added to the waitlist.')
            ->assertSee('Add to Waitlist')
            ->assertDontSee('Seat selected table')
            ->call('closeWalkInModal')
            ->assertSet('showWalkInModal', false)
            ->assertDontSee('Register Walk-in');
    }

    public function test_waitlist_page_closes_walk_in_modal_after_registration_event(): void
    {
        Livewire::actingAs($this->user('admin'))
            ->test(WaitlistPanel::class)
            ->call('openWalkInModal')
            ->assertSet('showWalkInModal', true)
            ->call('completeWalkInRegistration')
            ->assertSet('showWalkInModal', false)
            ->assertDispatched('tables-refresh');
    }

    public function test_legacy_staff_queue_route_returns_to_waitlist_management(): void
    {
        $this->actingAs($this->user('staff'))
            ->get(route('staff.queue'))
            ->assertRedirect(route('admin.waitlist'));
    }

    public function test_waitlist_management_combines_floor_map_and_waitlist_operations(): void
    {
        $table = $this->table(capacity: 4, status: 'available');
        Seat::create([
            'table_id' => $table->id,
            'seat_index' => 1,
            'status' => 'free',
            'pos_x' => 25,
            'pos_y' => 30,
        ]);

        $this->queueEntry(['customer_name' => 'Operations Guest']);

        $this->actingAs($this->user('admin'))
            ->get(route('admin.waitlist'))
            ->assertOk()
            ->assertSee('Floor Map Panel')
            ->assertSeeHtml('data-blueprint-floor-map')
            ->assertSeeHtml('data-waitlist-root')
            ->assertSee('Operations Guest')
            ->assertSee('Add Walk-in');
    }

    public function test_floor_map_marker_can_open_table_first_waitlist_seating(): void
    {
        $table = $this->table(capacity: 4, status: 'available');
        $entry = $this->queueEntry([
            'customer_name' => 'Seat From Marker Guest',
            'party_size' => 2,
        ]);

        Livewire::actingAs($this->user('admin'))
            ->test(WaitlistPanel::class)
            ->call('openFloorMapSeatWaitlistGuest', ['tableId' => $table->id])
            ->assertSet('floorSeatTableId', $table->id)
            ->assertSee('Seat waitlist guest at '.$table->label)
            ->assertSee('Seat From Marker Guest')
            ->call('seatWaitingGuestAtFloorTable', $entry->id)
            ->assertSet('floorSeatTableId', null)
            ->assertDispatched('notify', type: 'success', message: 'Guest seated.')
            ->assertDispatched('tables-refresh');

        $this->assertSame('seated', $entry->refresh()->status);
        $this->assertSame('occupied', $table->refresh()->status);
    }

    public function test_floor_map_marker_rejects_non_free_table_for_waitlist_seating(): void
    {
        $table = $this->table(capacity: 4, status: 'occupied');

        Livewire::actingAs($this->user('admin'))
            ->test(WaitlistPanel::class)
            ->call('openFloorMapSeatWaitlistGuest', ['tableId' => $table->id])
            ->assertSet('floorSeatTableId', null)
            ->assertDispatched('notify', type: 'error', message: 'Choose a free table before seating a waitlist guest.');
    }

    public function test_clicking_waitlist_guest_highlights_compatible_floor_map_tables(): void
    {
        $small = $this->table(capacity: 1, status: 'available');
        $fit = $this->table(capacity: 4, status: 'available');
        $occupied = $this->table(capacity: 4, status: 'occupied');
        $entry = $this->queueEntry([
            'customer_name' => 'Map Match Guest',
            'party_size' => 2,
        ]);

        Livewire::actingAs($this->user('admin'))
            ->test(WaitlistPanel::class)
            ->call('highlightCompatibleTablesForEntry', $entry->id)
            ->assertSet('highlightedQueueEntryId', $entry->id)
            ->assertDispatched('operations-highlight-compatible-tables', tableIds: [$fit->id]);
    }

    public function test_eta_is_zero_only_when_guest_has_immediate_compatible_table(): void
    {
        $this->table(capacity: 4, status: 'available');

        $entry = app(QueueService::class)->join('Immediate Guest', '', 2, 'none', 'staff', 'desktop');

        $entry->refresh();
        $this->assertSame(0, $entry->estimated_wait);
        $this->assertSame(0, $entry->last_estimated_wait);
    }

    public function test_priority_queue_recalculates_eta_for_regular_guest(): void
    {
        $this->table(capacity: 4, status: 'occupied', attrs: [
            'occupied_at' => now()->subMinutes(5),
            'occupied_party' => 2,
        ]);

        $queue = app(QueueService::class);
        $regular = $queue->join('Regular Guest', '', 2, 'none', 'staff', 'desktop');
        $pwd = $queue->join('PWD Guest', '', 2, 'pwd', 'staff', 'desktop');

        $pwd->refresh();
        $regular->refresh();

        $this->assertSame(10, $pwd->estimated_wait);
        $this->assertSame(10, $pwd->last_estimated_wait);
        $this->assertSame(20, $regular->estimated_wait);
        $this->assertSame(20, $regular->last_estimated_wait);
    }

    public function test_table_status_change_refreshes_waitlist_eta(): void
    {
        Setting::set('automation_notify_queue_on_release', '0');
        $table = $this->table(capacity: 4, status: 'occupied', attrs: [
            'occupied_at' => now()->subMinutes(5),
            'occupied_party' => 2,
        ]);

        $entry = app(QueueService::class)->join('Waiting Guest', '', 2, 'none', 'staff', 'desktop');
        $this->assertSame(10, $entry->refresh()->estimated_wait);

        app(TableService::class)->override($table->id, 'available');

        $this->assertSame(0, $entry->refresh()->estimated_wait);
        $this->assertSame(0, $entry->last_estimated_wait);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    private function table(int $capacity, string $status, array $attrs = []): Table
    {
        return Table::create(array_merge([
            'venue_id' => 1,
            'label' => 'T'.(Table::count() + 1),
            'capacity' => $capacity,
            'status' => $status,
        ], $attrs));
    }

    private function queueEntry(array $attrs = []): QueueEntry
    {
        return QueueEntry::create(array_merge([
            'customer_name' => 'Walkin Guest',
            'customer_phone' => '09171234567',
            'party_size' => 2,
            'priority_type' => 'none',
            'priority_score' => 0,
            'needs_accessible' => false,
            'status' => 'waiting',
            'estimated_wait' => 10,
            'last_estimated_wait' => 10,
            'joined_at' => now(),
        ], $attrs));
    }
}
