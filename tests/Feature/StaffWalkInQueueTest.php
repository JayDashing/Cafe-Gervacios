<?php

namespace Tests\Feature;

use App\Livewire\StaffWalkInQueue;
use App\Models\QueueEntry;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StaffWalkInQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_register_walk_in_with_valid_details(): void
    {
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

        Livewire::actingAs($user)
            ->test(StaffWalkInQueue::class)
            ->set('customer_name', 'Walkin Test')
            ->set('customer_phone', '09171234567')
            ->set('party_size', 3)
            ->set('priority_type', 'none')
            ->call('register')
            ->assertHasNoErrors()
            ->assertDispatched('notify', type: 'success', message: 'Added to queue. Ticket #1');

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
    }
}
