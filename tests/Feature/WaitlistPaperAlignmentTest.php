<?php

namespace Tests\Feature;

use App\Jobs\SendSmsJob;
use App\Livewire\Admin\WaitlistPanel;
use App\Livewire\StaffWalkInQueue;
use App\Models\QueueEntry;
use App\Models\Table;
use App\Models\User;
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
