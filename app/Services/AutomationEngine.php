<?php

namespace App\Services;

use App\Jobs\SendSmsJob;
use App\Models\AutomationLog;
use App\Models\Booking;
use App\Models\QueueEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AutomationEngine
{
    public static function run(string $task): void
    {
        // Queue hold expiry must run even when master automation is off; it is gated by
        // automation_queue_hold_enabled inside expireQueueHolds().
        if ($task === 'queue_holds') {
            try {
                self::expireQueueHolds();
            } catch (\Throwable $e) {
                Log::error('Automation failed', ['task' => $task, 'error' => $e->getMessage()]);
                AutomationLog::record($task, $e->getMessage(), ['exception' => $e::class], false);
                self::notifyAdminFailure($task, $e->getMessage());
            }

            return;
        }

        if ($task === 'reservation_table_release') {
            try {
                self::releaseCancelledOrFailedReservationTables();
            } catch (\Throwable $e) {
                Log::error('Automation failed', ['task' => $task, 'error' => $e->getMessage()]);
                AutomationLog::record($task, $e->getMessage(), ['exception' => $e::class], false);
                self::notifyAdminFailure($task, $e->getMessage());
            }

            return;
        }

        if (! AutomationSettings::masterEnabled()) {
            return;
        }

        try {
            match ($task) {
                'wait_estimates' => self::refreshWaitEstimates(),
                'no_shows' => self::markNoShows(),
                'late_checkin' => self::lateCheckinSms(),
                'reminders' => self::bookingReminders(),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::error('Automation failed', ['task' => $task, 'error' => $e->getMessage()]);
            AutomationLog::record($task, $e->getMessage(), ['exception' => $e::class], false);
            self::notifyAdminFailure($task, $e->getMessage());
        }
    }

    public static function notifyAdminFailure(string $task, string $message): void
    {
        if (! AutomationSettings::bool('automation_alert_admin_on_error', true)) {
            return;
        }

        $phone = AutomationSettings::adminAlertPhone();
        if ($phone === '') {
            return;
        }

        dispatch(new SendSmsJob($phone, 'automation_error', [
            'task' => $task,
            'message' => \Illuminate\Support\Str::limit($message, 120),
            'venue' => config('app.name', 'Café Gervacios'),
        ]));
    }

    /**
     * Release tables still held for reservation bookings that are cancelled or failed.
     *
     * This does **not** release paid holds based on time. The reservation hold window
     * (`operations.reservation_hold_window_hours`, default 5) is policy only: paid active/pending
     * bookings keep the table reserved whether the slot is more than that many hours away or within it.
     * whether the slot is before or inside that window (including more than 5 hours away).
     * Only cancelled bookings or failed payment free reserved tables here.
     */
    public static function releaseCancelledOrFailedReservationTables(): void
    {
        $bookings = Booking::query()
            ->with('table')
            ->whereNotNull('table_id')
            ->where(function ($q) {
                $q->where('status', 'cancelled')
                    ->orWhere('payment_status', 'failed');
            })
            ->where('updated_at', '>=', now()->subDay())
            ->get();

        foreach ($bookings as $booking) {
            $table = $booking->table;
            if ($table === null || $table->status !== 'reserved') {
                continue;
            }
            if ($table->booking_id !== null && (int) $table->booking_id !== (int) $booking->id) {
                continue;
            }

            try {
                app(TableService::class)->releaseTable((int) $table->id);
                $table->refresh()->update(['booking_id' => null]);
                $booking->update(['table_id' => null]);
                AutomationLog::record('reservation_table_release', 'Released reserved table for cancelled/failed booking', [
                    'booking_id' => $booking->id,
                    'table_id' => $table->id,
                ]);
            } catch (\Throwable $e) {
                Log::warning('reservation_table_release', [
                    'booking_id' => $booking->id,
                    'table_id' => $table->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public static function expireQueueHolds(): void
    {
        if (! AutomationSettings::bool('automation_queue_hold_enabled', true)) {
            return;
        }

        $queue = app(QueueService::class);

        $entries = QueueEntry::query()
            ->where('status', 'notified')
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<', now())
            ->get();

        foreach ($entries as $entry) {
            $queue->finalizeExpiredNotifiedHold($entry);
        }
    }

    public static function refreshWaitEstimates(): void
    {
        if (! AutomationSettings::bool('automation_wait_sms_enabled', true)) {
            return;
        }

        $queue = app(QueueService::class);
        $jump = AutomationSettings::int('automation_wait_increase_minutes', (int) config('automation.wait_increase_alert_minutes', 10));

        $waiting = QueueEntry::waiting()->get();
        $oldWaits = $waiting->mapWithKeys(fn (QueueEntry $entry) => [
            $entry->id => (int) ($entry->last_estimated_wait ?? $entry->estimated_wait ?? 0),
        ]);
        $newWaits = $queue->refreshEstimatedWaits();

        foreach ($waiting as $entry) {
            $new = (int) ($newWaits[$entry->id] ?? $entry->refresh()->estimated_wait ?? 0);
            $old = (int) ($entry->last_estimated_wait ?? $entry->estimated_wait ?? 0);
            $old = (int) ($oldWaits[$entry->id] ?? $old);

            if ($old > 0 && $new >= $old + $jump && $entry->wait_alert_sent_at === null) {
                dispatch(new SendSmsJob($entry->customer_phone, 'wait_extended', [
                    'name' => $entry->customer_name,
                    'wait' => $new,
                    'venue' => config('app.venue_name', config('app.name')),
                ]));
                $entry->update(['wait_alert_sent_at' => now()]);
                AutomationLog::record('wait_estimates', 'Wait extended SMS', ['entry_id' => $entry->id]);
            }
        }
    }

    public static function markNoShows(): void
    {
        if (! AutomationSettings::bool('automation_no_show_enabled', true)) {
            return;
        }

        $mins = AutomationSettings::int('automation_no_show_minutes', (int) config('automation.no_show_minutes_after_booking', 30));

        $bookings = Booking::query()
            ->with('table')
            ->whereIn('status', ['active', 'pending'])
            ->where('payment_status', '!=', 'pending_verification')
            ->whereNull('checked_in_at')
            ->whereNull('no_show_at')
            ->where('booked_at', '<', now()->subMinutes($mins))
            ->get();

        foreach ($bookings as $booking) {
            if ($booking->payment_status === 'pending' && $booking->status === 'pending') {
                continue;
            }

            $tableId = $booking->table_id;
            $noShowLogMessage = 'No-show marked';
            $noShowLogPayload = ['booking_id' => $booking->id];

            if ($tableId) {
                try {
                    $table = $booking->table;
                    if ($table !== null && $table->status === 'reserved') {
                        app(TableService::class)->noShow((int) $tableId);
                        $noShowLogMessage = 'No-show marked; released reserved table to available';
                        $noShowLogPayload['table_label'] = $table->label;
                    } elseif ($table !== null && $table->status === 'occupied') {
                        app(TableService::class)->sendToCleaning((int) $tableId);
                    }
                } catch (\Throwable) {
                    // ignore
                }
            }

            $booking->update([
                'status' => 'cancelled',
                'no_show_at' => now(),
                'table_id' => null,
            ]);

            $noShowLogPayload['notification'] = app(BookingNoShowNotifier::class)->send($booking);

            AutomationLog::record('no_shows', $noShowLogMessage, $noShowLogPayload);
            app(QueueService::class)->notifyNextAfterTableRelease();
        }
    }

    public static function lateCheckinSms(): void
    {
        if (! AutomationSettings::bool('automation_late_checkin_enabled', true)) {
            return;
        }

        $mins = AutomationSettings::int('automation_late_checkin_minutes', (int) config('automation.late_checkin_minutes_after_slot', 15));

        $bookings = Booking::query()
            ->whereIn('status', ['active', 'pending'])
            ->where('payment_status', '!=', 'pending_verification')
            ->whereNull('checked_in_at')
            ->whereNull('late_checkin_sms_sent_at')
            ->whereDate('booked_at', today())
            ->get();

        foreach ($bookings as $booking) {
            if ($booking->status === 'pending' && $booking->payment_status === 'pending') {
                continue;
            }

            $slot = Carbon::parse($booking->booked_at);
            if (now()->lt($slot->copy()->addMinutes($mins))) {
                continue;
            }

            dispatch(new SendSmsJob($booking->customer_phone, 'late_checkin', [
                'name' => $booking->customer_name,
                'venue' => config('app.venue_name', config('app.name')),
                'ref' => $booking->booking_ref,
            ]));

            $booking->update(['late_checkin_sms_sent_at' => now()]);
            AutomationLog::record('late_checkin', 'SMS sent', ['booking_id' => $booking->id]);
        }
    }

    public static function bookingReminders(): void
    {
        if (! AutomationSettings::bool('automation_reminders_enabled', true)) {
            return;
        }

        $h24 = AutomationSettings::int('automation_reminder_hours_1', (int) config('automation.reminder_hours_before_1', 24));
        $h2 = AutomationSettings::int('automation_reminder_hours_2', (int) config('automation.reminder_hours_before_2', 2));
        $slack = 15;

        $bookings = Booking::query()
            ->whereIn('status', ['active', 'pending'])
            ->where('payment_status', 'paid')
            ->where('booked_at', '>=', now())
            ->where('booked_at', '<=', now()->addHours(25))
            ->get();

        foreach ($bookings as $booking) {
            $at = Carbon::parse($booking->booked_at);
            if ($at->isPast()) {
                continue;
            }

            $n = now();
            $t24 = $at->copy()->subHours($h24);
            if ($booking->reminder_24h_sent_at === null && $n->between($t24->copy()->subMinutes($slack), $t24->copy()->addMinutes($slack))) {
                dispatch(new SendSmsJob($booking->customer_phone, 'reminder_24h', [
                    'name' => $booking->customer_name,
                    'venue' => config('app.venue_name', config('app.name')),
                    'ref' => $booking->booking_ref,
                    'time' => $at->format('M j, g:i A'),
                ]));
                $booking->update(['reminder_24h_sent_at' => now()]);
                AutomationLog::record('reminders', '24h reminder', ['booking_id' => $booking->id]);
            }

            $t2 = $at->copy()->subHours($h2);
            if ($booking->reminder_2h_sent_at === null && $n->between($t2->copy()->subMinutes($slack), $t2->copy()->addMinutes($slack))) {
                dispatch(new SendSmsJob($booking->customer_phone, 'reminder_2h', [
                    'name' => $booking->customer_name,
                    'venue' => config('app.venue_name', config('app.name')),
                    'ref' => $booking->booking_ref,
                    'time' => $at->format('M j, g:i A'),
                ]));
                $booking->update(['reminder_2h_sent_at' => now()]);
                AutomationLog::record('reminders', '2h reminder', ['booking_id' => $booking->id]);
            }
        }
    }
}
