<?php

namespace Tests\Feature;

use App\Jobs\SendSmsJob;
use App\Models\AutomationLog;
use App\Models\Booking;
use App\Models\QueueEntry;
use App\Models\Setting;
use App\Models\SmsLog;
use App\Models\Table;
use App\Models\User;
use App\Services\AutomationEngine;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AutomationPaperAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_notified_hold_runs_even_when_master_is_disabled(): void
    {
        Queue::fake([SendSmsJob::class]);
        Setting::set('automation_master_enabled', '0');
        Setting::set('automation_queue_hold_enabled', '1');
        Setting::set('automation_notify_queue_on_release', '0');

        $table = $this->table(['status' => 'reserved']);
        $entry = $this->queueEntry([
            'status' => 'notified',
            'reserved_table_id' => $table->id,
            'hold_expires_at' => now()->subMinute(),
            'hold_confirmation_code' => 'ABC123',
        ]);

        AutomationEngine::run('queue_holds');

        $this->assertSame('cancelled', $entry->refresh()->status);
        $this->assertNotNull($entry->skipped_at);
        $this->assertNull($entry->reserved_table_id);
        $this->assertSame('available', $table->refresh()->status);
        $this->assertDatabaseHas('automation_logs', ['task' => 'queue_holds', 'success' => true]);
        Queue::assertPushed(SendSmsJob::class, 1);
    }

    public function test_wait_estimates_refresh_and_send_extended_wait_alert(): void
    {
        Queue::fake([SendSmsJob::class]);
        Setting::set('automation_master_enabled', '1');
        Setting::set('automation_wait_sms_enabled', '1');
        Setting::set('automation_wait_increase_minutes', '10');
        $this->table([
            'status' => 'occupied',
            'capacity' => 4,
            'occupied_at' => now()->subMinutes(5),
            'occupied_party' => 2,
        ]);
        $this->queueEntry([
            'customer_name' => 'Ahead Guest',
            'customer_phone' => '09170000001',
            'joined_at' => now()->subMinute(),
        ]);
        $entry = $this->queueEntry([
            'customer_name' => 'Delayed Guest',
            'customer_phone' => '09170000002',
            'estimated_wait' => 5,
            'last_estimated_wait' => 5,
            'joined_at' => now(),
        ]);

        AutomationEngine::run('wait_estimates');

        $entry->refresh();
        $this->assertGreaterThanOrEqual(15, $entry->estimated_wait);
        $this->assertSame($entry->estimated_wait, $entry->last_estimated_wait);
        $this->assertNotNull($entry->wait_alert_sent_at);
        $this->assertDatabaseHas('automation_logs', ['task' => 'wait_estimates', 'message' => 'Wait extended SMS']);
        Queue::assertPushed(SendSmsJob::class, 1);
    }

    public function test_no_show_marks_booking_releases_table_sends_sms_and_logs(): void
    {
        Queue::fake([SendSmsJob::class]);
        Setting::set('automation_master_enabled', '1');
        Setting::set('automation_no_show_enabled', '1');
        Setting::set('automation_no_show_minutes', '30');
        Setting::set('automation_notify_queue_on_release', '0');

        $table = $this->table(['status' => 'reserved']);
        $booking = $this->booking([
            'table_id' => $table->id,
            'status' => 'active',
            'payment_status' => 'paid',
            'booked_at' => now()->subMinutes(45),
        ]);
        $table->update(['booking_id' => $booking->id]);

        AutomationEngine::run('no_shows');

        $booking->refresh();
        $this->assertSame('cancelled', $booking->status);
        $this->assertNotNull($booking->no_show_at);
        $this->assertNull($booking->table_id);
        $this->assertSame('available', $table->refresh()->status);
        $this->assertDatabaseHas('automation_logs', ['task' => 'no_shows']);
        Queue::assertPushed(SendSmsJob::class, 1);
    }

    public function test_late_check_in_and_reservation_reminders_send_once(): void
    {
        Queue::fake([SendSmsJob::class]);
        Setting::set('automation_master_enabled', '1');
        Setting::set('automation_late_checkin_enabled', '1');
        Setting::set('automation_late_checkin_minutes', '15');
        Setting::set('automation_reminders_enabled', '1');

        $this->travelTo(now()->startOfMinute());

        $late = $this->booking([
            'booking_ref' => 'GRV-LATE01',
            'status' => 'active',
            'payment_status' => 'paid',
            'booked_at' => now()->subMinutes(20),
        ]);
        $reminder24 = $this->booking([
            'booking_ref' => 'GRV-REM24',
            'status' => 'active',
            'payment_status' => 'paid',
            'booked_at' => now()->addHours(24),
        ]);
        $reminder2 = $this->booking([
            'booking_ref' => 'GRV-REM02',
            'status' => 'active',
            'payment_status' => 'paid',
            'booked_at' => now()->addHours(2),
        ]);

        AutomationEngine::run('late_checkin');
        AutomationEngine::run('reminders');

        $this->assertNotNull($late->refresh()->late_checkin_sms_sent_at);
        $this->assertNotNull($reminder24->refresh()->reminder_24h_sent_at);
        $this->assertNotNull($reminder2->refresh()->reminder_2h_sent_at);
        $this->assertDatabaseHas('automation_logs', ['task' => 'late_checkin', 'message' => 'SMS sent']);
        $this->assertDatabaseHas('automation_logs', ['task' => 'reminders', 'message' => '24h reminder']);
        $this->assertDatabaseHas('automation_logs', ['task' => 'reminders', 'message' => '2h reminder']);
        Queue::assertPushed(SendSmsJob::class, 3);
    }

    public function test_cancelled_or_failed_reservation_releases_reserved_table(): void
    {
        Setting::set('automation_notify_queue_on_release', '0');

        $table = $this->table(['status' => 'reserved']);
        $booking = $this->booking([
            'table_id' => $table->id,
            'status' => 'cancelled',
            'payment_status' => 'failed',
            'booked_at' => now()->addHour(),
        ]);
        $table->update(['booking_id' => $booking->id]);

        AutomationEngine::run('reservation_table_release');

        $this->assertSame('available', $table->refresh()->status);
        $this->assertNull($booking->refresh()->table_id);
        $this->assertDatabaseHas('automation_logs', ['task' => 'reservation_table_release']);
    }

    public function test_general_automation_skips_when_master_is_disabled(): void
    {
        Queue::fake([SendSmsJob::class]);
        Setting::set('automation_master_enabled', '0');
        Setting::set('automation_wait_sms_enabled', '1');
        $this->table([
            'status' => 'occupied',
            'capacity' => 4,
            'occupied_at' => now()->subMinutes(5),
        ]);
        $entry = $this->queueEntry(['last_estimated_wait' => 5]);

        AutomationEngine::run('wait_estimates');

        $this->assertSame(5, $entry->refresh()->last_estimated_wait);
        Queue::assertNothingPushed();
    }

    public function test_automation_failure_records_log_and_alerts_admin(): void
    {
        Queue::fake([SendSmsJob::class]);
        Setting::set('automation_alert_admin_on_error', '1');
        Setting::set('admin_alert_phone', '09179999999');
        $this->queueEntry([
            'status' => 'notified',
            'hold_expires_at' => now()->subMinute(),
        ]);

        $this->app->bind(QueueService::class, fn () => new class {
            public function finalizeExpiredNotifiedHold(QueueEntry $entry): void
            {
                throw new \RuntimeException('forced failure');
            }
        });

        AutomationEngine::run('queue_holds');

        $this->assertTrue(AutomationLog::where('task', 'queue_holds')->where('success', false)->exists());
        Queue::assertPushed(SendSmsJob::class, 1);
    }

    public function test_qa_proof_panel_shows_automation_log_with_sms_result(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $booking = $this->booking([
            'booking_ref' => 'GRV-AUTPROOF',
            'booked_at' => now()->subMinutes(20),
        ]);

        AutomationLog::record('late_checkin', 'SMS sent', ['booking_id' => $booking->id]);
        SmsLog::create([
            'phone_hash' => hash('sha256', 'proof'),
            'phone' => '09171112222',
            'message' => 'Late check-in reminder',
            'status' => 'queued',
            'semaphore_message_id' => 'sms-proof-1',
            'error_message' => null,
            'template' => 'late_checkin',
            'context' => ['ref' => 'GRV-AUTPROOF'],
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.system-logs'))
            ->assertOk()
            ->assertSee('Automation runs through Laravel scheduler. This page shows recorded system actions after they occur.')
            ->assertSee('Late Check-in SMS')
            ->assertSee('GRV-AUTPROOF')
            ->assertSee('SMS queued')
            ->assertSee('sms_logs #1');
    }

    private function table(array $attrs = []): Table
    {
        return Table::create(array_merge([
            'venue_id' => 1,
            'label' => 'T'.(Table::count() + 1),
            'capacity' => 4,
            'status' => 'available',
        ], $attrs));
    }

    private function queueEntry(array $attrs = []): QueueEntry
    {
        return QueueEntry::create(array_merge([
            'customer_name' => 'Queue Guest',
            'customer_phone' => '09171234567',
            'party_size' => 2,
            'priority_type' => 'none',
            'priority_score' => 0,
            'needs_accessible' => false,
            'status' => 'waiting',
            'estimated_wait' => 5,
            'last_estimated_wait' => 5,
            'joined_at' => now(),
        ], $attrs));
    }

    private function booking(array $attrs = []): Booking
    {
        $table = $attrs['table_id'] ?? $this->table()->id;

        return Booking::create(array_merge([
            'booking_ref' => 'GRV-'.str_pad((string) (Booking::count() + 1), 7, '0', STR_PAD_LEFT),
            'table_id' => $table,
            'customer_name' => 'Booking Guest',
            'customer_phone' => '09171112222',
            'party_size' => 2,
            'priority_type' => 'none',
            'status' => 'active',
            'payment_status' => 'paid',
            'booked_at' => now(),
        ], $attrs));
    }
}
