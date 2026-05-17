<?php

namespace Tests\Feature;

use App\Livewire\Admin\SeatingAnalytics;
use App\Models\Booking;
use App\Models\QueueEntry;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsPaperAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_counts_charts_and_top_tables_match_expected_data(): void
    {
        $occupied = $this->table('Occupied', 'occupied');
        $available = $this->table('Available', 'available');
        $extra = $this->table('Extra', 'available');

        $this->booking($occupied, ['booking_ref' => 'GRV-TODAY', 'booked_at' => now()->setTime(9, 0)]);
        $this->booking($available, [
            'booking_ref' => 'GRV-CHECK',
            'booked_at' => now()->setTime(10, 0),
            'checked_in_at' => now(),
        ]);
        $this->booking($extra, [
            'booking_ref' => 'GRV-OLD',
            'booked_at' => now()->subDays(10),
        ]);
        QueueEntry::create([
            'customer_name' => 'Seated Queue',
            'customer_phone' => '09170000001',
            'party_size' => 2,
            'priority_type' => 'none',
            'priority_score' => 0,
            'needs_accessible' => false,
            'status' => 'seated',
            'joined_at' => now()->subHour(),
            'seated_at' => now(),
        ]);

        $analytics = new SeatingAnalytics();

        $this->assertSame(2, $analytics->totalBookingsToday());
        $this->assertSame(1, $analytics->totalCheckedInToday());
        $this->assertSame(1, $analytics->totalSeatedFromQueue());
        $this->assertSame(1, $analytics->tablesOccupiedNow());
        $this->assertSame(2, $analytics->tablesFreeNow());

        $peak = $analytics->peakHourData();
        $this->assertCount(24, $peak);
        $this->assertSame(1, $peak[9]);
        $this->assertSame(1, $peak[10]);

        $labels = $analytics->peakHourLabels();
        $this->assertCount(24, $labels);
        $this->assertSame('12 AM', $labels[0]);
        $this->assertSame('11 PM', $labels[23]);
    }

    public function test_top_table_usage_returns_top_five_in_descending_order(): void
    {
        $tables = [];
        foreach (range(1, 6) as $i) {
            $tables[$i] = $this->table('T'.$i, 'available');
            foreach (range(1, $i) as $j) {
                $this->booking($tables[$i], [
                    'booking_ref' => 'GRV-T'.$i.$j,
                    'booked_at' => now()->subDay(),
                ]);
            }
        }

        $top = (new SeatingAnalytics())->topTableUsage();

        $this->assertCount(5, $top);
        $this->assertSame(['T6', 'T5', 'T4', 'T3', 'T2'], array_column($top, 'label'));
        $this->assertSame([6, 5, 4, 3, 2], array_column($top, 'count'));
    }

    public function test_analytics_page_handles_no_data_and_restricts_unauthorized_users(): void
    {
        $analytics = new SeatingAnalytics();

        $this->assertSame(0, $analytics->totalBookingsToday());
        $this->assertSame(0, $analytics->totalCheckedInToday());
        $this->assertSame(0, $analytics->totalSeatedFromQueue());
        $this->assertCount(24, $analytics->peakHourData());
        $this->assertSame([], $analytics->topTableUsage());

        $this->actingAs($this->user('staff'))
            ->get(route('admin.seating-analytics'))
            ->assertForbidden();

        $this->actingAs($this->user('admin'))
            ->get(route('admin.seating-analytics'))
            ->assertOk()
            ->assertSee('Total bookings today')
            ->assertSee('Checked-in bookings')
            ->assertSee('Seated from queue')
            ->assertSee('Occupied tables')
            ->assertSee('Available tables')
            ->assertSee('Last updated')
            ->assertSee('No analytics data yet.');
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    private function table(string $label, string $status): Table
    {
        return Table::create([
            'venue_id' => 1,
            'label' => $label,
            'capacity' => 4,
            'status' => $status,
        ]);
    }

    private function booking(Table $table, array $attrs = []): Booking
    {
        return Booking::create(array_merge([
            'booking_ref' => 'GRV-'.str_pad((string) (Booking::count() + 1), 7, '0', STR_PAD_LEFT),
            'table_id' => $table->id,
            'customer_name' => 'Analytics Guest',
            'customer_phone' => '0917'.str_pad((string) (Booking::count() + 1), 7, '0', STR_PAD_LEFT),
            'party_size' => 2,
            'priority_type' => 'none',
            'status' => 'active',
            'payment_status' => 'paid',
            'booked_at' => now(),
        ], $attrs));
    }
}
