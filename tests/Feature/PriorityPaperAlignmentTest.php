<?php

namespace Tests\Feature;

use App\Models\AdminLog;
use App\Models\QueueEntry;
use App\Models\Setting;
use App\Models\Table;
use App\Models\User;
use App\Services\PriorityService;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class PriorityPaperAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_priority_scores_match_policy(): void
    {
        $service = app(PriorityService::class);

        $this->assertSame(100, $service->getScore('pwd'));
        $this->assertSame(100, $service->getScore('pregnant'));
        $this->assertSame(100, $service->getScore('senior'));
        $this->assertSame(0, $service->getScore('none'));
    }

    public function test_priority_guests_sort_before_regular_guests(): void
    {
        $regular = $this->queueEntry('Regular', 'none', now()->subMinutes(10));
        $priority = $this->queueEntry('Senior', 'senior', now());

        $sorted = QueueEntry::waiting()->sorted()->pluck('id')->all();

        $this->assertSame([$priority->id, $regular->id], $sorted);
    }

    public function test_accessible_table_rule_only_blocks_pwd_when_enabled(): void
    {
        $standard = $this->table(false);
        $accessible = $this->table(true);
        $queue = app(QueueService::class);

        Setting::set('queue_pwd_requires_accessible_table', '1');
        $pwd = $this->queueEntry('PWD', 'pwd', now());
        $this->assertFalse($queue->tableFitsEntry($pwd, $standard));
        $this->assertTrue($queue->tableFitsEntry($pwd, $accessible));

        $senior = $this->queueEntry('Senior', 'senior', now());
        $pregnant = $this->queueEntry('Pregnant', 'pregnant', now());
        $this->assertTrue($queue->tableFitsEntry($senior, $standard));
        $this->assertTrue($queue->tableFitsEntry($pregnant, $standard));

        Setting::set('queue_pwd_requires_accessible_table', '0');
        $pwdStandardAllowed = $this->queueEntry('PWD Allowed', 'pwd', now());
        $this->assertTrue($queue->tableFitsEntry($pwdStandardAllowed, $standard));
    }

    public function test_priority_seating_records_audit_log(): void
    {
        $logger = Mockery::mock();
        $logger->shouldReceive('info')
            ->once()
            ->with('Priority seating event', Mockery::on(function (array $context) {
                return $context['priority_type'] === 'pwd'
                    && $context['accessible'] === true
                    && array_key_exists('wait_minutes', $context);
            }));
        Log::shouldReceive('channel')->once()->with('priority_audit')->andReturn($logger);

        $table = $this->table(true);
        $entry = $this->queueEntry('PWD Guest', 'pwd', now()->subMinutes(5), [
            'needs_accessible' => true,
        ]);

        app(QueueService::class)->seat($entry->id, $table->id);

        $this->assertSame('seated', $entry->refresh()->status);
        $this->assertSame('occupied', $table->refresh()->status);
    }

    public function test_priority_browser_proof_shows_score_accessibility_and_seating_log(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        Setting::set('queue_pwd_requires_accessible_table', '1');

        $table = $this->table(true);
        $entry = $this->queueEntry('PWD Proof', 'pwd', now()->subMinutes(5), [
            'needs_accessible' => true,
        ]);
        $regular = $this->queueEntry('Regular Proof', 'none', now()->subMinutes(10));

        $this->actingAs($admin)
            ->get(route('admin.waitlist'))
            ->assertOk()
            ->assertSeeInOrder(['PWD Proof', 'Waiting Guests - Regular', 'Regular Proof'])
            ->assertSee('Priority Score')
            ->assertSee('100')
            ->assertSee('Accessible table required');

        app(QueueService::class)->seat($entry->id, $table->id);

        $this->assertTrue(AdminLog::where('action', 'priority_seating')->where('target_id', $entry->id)->exists());

        $this->actingAs($admin)
            ->get(route('admin.system-logs'))
            ->assertOk()
            ->assertSee('Priority seating action')
            ->assertSee('Score 100')
            ->assertSee('Accessible table yes');
    }

    private function queueEntry(string $name, string $priorityType, mixed $joinedAt, array $attrs = []): QueueEntry
    {
        $score = app(PriorityService::class)->getScore($priorityType);

        return QueueEntry::create(array_merge([
            'customer_name' => $name,
            'customer_phone' => '0917'.str_pad((string) (QueueEntry::count() + 1), 7, '0', STR_PAD_LEFT),
            'party_size' => 2,
            'priority_type' => $priorityType,
            'priority_score' => $score,
            'needs_accessible' => app(PriorityService::class)->requiresAccessibleTable($priorityType),
            'status' => 'waiting',
            'estimated_wait' => 0,
            'last_estimated_wait' => 0,
            'joined_at' => $joinedAt,
        ], $attrs));
    }

    private function table(bool $accessible): Table
    {
        return Table::create([
            'venue_id' => 1,
            'label' => 'T'.(Table::count() + 1),
            'capacity' => 4,
            'status' => 'available',
            'is_accessible' => $accessible,
        ]);
    }
}
