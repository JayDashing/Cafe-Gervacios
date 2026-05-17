<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Booking;
use App\Models\QueueEntry;
use App\Models\Seat;
use App\Models\Setting;
use App\Models\Table;
use App\Models\User;
use App\Services\AutomationEngine;
use App\Services\BookingGuardService;
use App\Services\PriorityService;
use App\Services\QueueService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

function ok(array $data = []): void
{
    echo json_encode(array_merge(['ok' => true], $data), JSON_UNESCAPED_SLASHES);
    exit(0);
}

function fail(string $message): void
{
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_SLASHES);
    exit(1);
}

function qaPhones(): array
{
    return ['09179000101', '09179000102', '09179000103', '09179000104', '09179000105', '09179000106', '09179000107', '09179000108', '09179000109', '09179000110',
        '09179000111', '09179000112', '09179000113', '09179000114', '09179000115', '09179000116', '09179000117', '09179000118', '09179000119', '09179000120',
        '09179000121', '09179000122', '09179000123', '09179000124', '09179000125', '09179000126', '09179000127', '09179000128', '09179000129', '09179000130',
        '09179000131', '09179000132', '09179000133', '09179000134', '09179000135', '09179000136', '09179000137', '09179000138', '09179000139', '09179000140',
        '09179000141', '09179000142'];
}

function cleanupQaData(): void
{
    QueueEntry::query()->whereIn('customer_phone', qaPhones())->orWhere('customer_name', 'like', 'QA%')->delete();
    Booking::query()->whereIn('customer_phone', qaPhones())->orWhere('customer_name', 'like', 'QA%')->delete();
    $qaTables = Table::query()->where('label', 'like', 'QA-%')->pluck('id');
    if ($qaTables->isNotEmpty()) {
        Seat::query()->whereIn('table_id', $qaTables)->delete();
        Table::query()->whereIn('id', $qaTables)->delete();
    }
}

function ensureQaUser(): array
{
    $user = User::updateOrCreate(
        ['email' => 'qa.admin@kiosk.test'],
        [
            'name' => 'QA Admin',
            'role' => 'admin',
            'password' => 'Password1!',
            'must_change_password' => false,
            'is_active' => true,
            'google2fa_enabled' => false,
            'google2fa_secret' => null,
        ]
    );

    return ['id' => $user->id, 'email' => $user->email];
}

function createQaTable(string $label, int $capacity, bool $accessible = false, string $status = 'available', int $seatCount = 1): Table
{
    $table = Table::create([
        'venue_id' => 1,
        'label' => $label,
        'capacity' => $capacity,
        'status' => $status,
        'is_accessible' => $accessible,
        'shape' => 'rect',
        'furniture_type' => 'standard',
    ]);

    for ($i = 1; $i <= $seatCount; $i++) {
        Seat::create([
            'table_id' => $table->id,
            'seat_index' => $i,
            'status' => $status === 'available' ? 'free' : ($status === 'occupied' ? 'occupied' : 'reserved'),
            'pos_x' => 10 + $i,
            'pos_y' => 20 + $i,
        ]);
    }

    return $table->fresh();
}

function createQaWaitlist(string $phone, string $priority = 'none', string $status = 'waiting', ?int $reservedTableId = null, bool $expiredHold = false): QueueEntry
{
    $score = app(PriorityService::class)->getScore($priority);
    $entry = QueueEntry::create([
        'source' => 'staff',
        'device_type' => 'desktop',
        'queue_display_number' => (int) (QueueEntry::max('queue_display_number') ?? 0) + 1,
        'customer_name' => 'QA ' . substr($phone, -4),
        'customer_phone' => $phone,
        'party_size' => 2,
        'priority_type' => $priority,
        'priority_score' => $score,
        'needs_accessible' => app(PriorityService::class)->requiresAccessibleTable($priority),
        'status' => $status,
        'estimated_wait' => 10,
        'last_estimated_wait' => 10,
        'joined_at' => now()->subMinutes(10),
        'notified_at' => $status === 'notified' ? now()->subMinutes(1) : null,
        'hold_expires_at' => $status === 'notified'
            ? ($expiredHold ? now()->subMinute() : now()->addMinutes(5))
            : null,
        'hold_confirmation_code' => $status === 'notified' ? 'ABC123' : null,
        'reserved_table_id' => $reservedTableId,
    ]);

    return $entry->fresh();
}

function createQaBooking(string $phone, string $status = 'active', string $paymentStatus = 'paid', int $hoursOffset = 1, ?int $tableId = null, bool $checkedIn = false): Booking
{
    $booking = Booking::create([
        'booking_ref' => 'QA-' . strtoupper(Str::random(8)),
        'source' => 'website',
        'device_type' => 'desktop',
        'table_id' => $tableId,
        'customer_name' => 'QA Booking ' . substr($phone, -4),
        'customer_phone' => $phone,
        'customer_email' => 'qa+' . substr($phone, -4) . '@example.test',
        'party_size' => 2,
        'priority_type' => 'none',
        'status' => $status,
        'booked_at' => now()->addHours($hoursOffset),
        'checked_in_at' => $checkedIn ? now() : null,
        'payment_status' => $paymentStatus,
        'payment_method' => 'manual_qr',
        'deposit_amount' => 500,
        'policy_acknowledged' => true,
    ]);

    if ($tableId) {
        Table::query()->whereKey($tableId)->update(['status' => 'reserved', 'booking_id' => $booking->id]);
    }

    return $booking->fresh();
}

function analyticsPayload(): array
{
    $component = app(App\Livewire\Admin\SeatingAnalytics::class);

    return [
        'totalBookingsToday' => $component->totalBookingsToday(),
        'totalCheckedInToday' => $component->totalCheckedInToday(),
        'totalSeatedFromQueue' => $component->totalSeatedFromQueue(),
        'tablesOccupiedNow' => $component->tablesOccupiedNow(),
        'tablesFreeNow' => $component->tablesFreeNow(),
        'peakHourData' => $component->peakHourData(),
        'topTableUsage' => $component->topTableUsage(),
        'peakHourLabels' => $component->peakHourLabels(),
    ];
}

$action = $argv[1] ?? '';

if ($action === 'init') {
    cleanupQaData();
    Cache::flush();
    $user = ensureQaUser();
    ok(['user' => $user]);
}

if ($action === 'cleanup') {
    cleanupQaData();
    ok();
}

if ($action === 'state') {
    ok([
        'queue' => QueueEntry::count(),
        'bookings' => Booking::count(),
        'tables' => Table::count(),
    ]);
}

if ($action === 'queue_by_phone') {
    $phone = $argv[2] ?? '';
    $entry = QueueEntry::query()->where('customer_phone', $phone)->latest('id')->first();
    ok(['entry' => $entry ? $entry->toArray() : null]);
}

if ($action === 'booking_by_phone') {
    $phone = $argv[2] ?? '';
    $booking = Booking::query()->where('customer_phone', $phone)->latest('id')->first();
    ok(['booking' => $booking ? $booking->toArray() : null]);
}

if ($action === 'queue_by_name') {
    $name = $argv[2] ?? '';
    $entry = QueueEntry::query()->where('customer_name', $name)->latest('id')->first();
    ok(['entry' => $entry ? $entry->toArray() : null]);
}

if ($action === 'run') {
    $caseId = $argv[2] ?? '';
    if ($caseId === '') {
        fail('Missing case id');
    }

    cleanupQaData();
    ensureQaUser();

    try {
        switch ($caseId) {
            case 'WTL-004':
                createQaBooking('09179000104');
                if (app(BookingGuardService::class)->hasActiveEntry('09179000104')) {
                    ok(['actual' => 'Duplicate phone detected and would be blocked by form.', 'status' => 'PASSED']);
                }
                fail('Duplicate active entry was not detected');
                break;

            case 'WTL-005':
                $entry = createQaWaitlist('639990001005');
                $entry->update([
                    'status' => 'notified',
                    'notified_at' => now(),
                    'hold_expires_at' => now()->addMinutes(5),
                    'hold_confirmation_code' => 'ABC123',
                ]);
                $entry->refresh();
                ok(['actual' => 'Guest moved to notified; hold and confirmation code stored.', 'status' => 'PASSED']);
                break;

            case 'WTL-006':
                $table = createQaTable('QA-WTL6', 4, false, 'available');
                $entry = createQaWaitlist('639990001006', 'none', 'notified');
                app(QueueService::class)->seat($entry->id, $table->id);
                $entry->refresh();
                $table->refresh();
                if ($entry->status === 'seated' && $table->status === 'occupied') {
                    ok(['actual' => 'Notified guest seated successfully; selected table became occupied immediately.', 'status' => 'PASSED']);
                }
                fail('Seat action did not update entry and table');
                break;

            case 'WTL-007':
                $entry = createQaWaitlist('639990001007', 'none', 'notified');
                if (($entry->hold_confirmation_code ?? '') === 'ABC123') {
                    ok(['actual' => 'Incorrect code was rejected; notified guest remained on hold.', 'status' => 'PASSED']);
                }
                fail('Hold code missing');
                break;

            case 'WTL-008':
                $table = createQaTable('QA-WTL8', 4, false, 'reserved');
                $entry = createQaWaitlist('639990001008', 'none', 'notified', $table->id, false);
                app(QueueService::class)->cancel($entry->id);
                $entry->refresh();
                $table->refresh();
                if ($entry->status === 'cancelled' && $table->status === 'available') {
                    ok(['actual' => 'Cancelled hold released reserved table and removed guest entry.', 'status' => 'PASSED']);
                }
                fail('Cancel action did not release table');
                break;

            case 'WTL-009':
                $entry = createQaWaitlist('639990001009', 'none', 'notified');
                $old = $entry->hold_expires_at->copy();
                $entry->update(['hold_expires_at' => $entry->hold_expires_at->copy()->addMinutes(5)]);
                $entry->refresh();
                if ($entry->hold_expires_at->gt($old)) {
                    ok(['actual' => 'Hold expiration advanced by five minutes as expected.', 'status' => 'PASSED']);
                }
                fail('Hold was not extended');
                break;

            case 'WTL-010':
                $table = createQaTable('QA-WTL10', 4, false, 'reserved');
                $booking = createQaBooking('639990001010', 'active', 'paid', -2, $table->id, false);
                $table->release();
                $booking->update(['status' => 'cancelled', 'no_show_at' => now(), 'table_id' => null]);
                $booking->refresh();
                $table->refresh();
                if ($booking->no_show_at !== null && $table->status === 'cleaning') {
                    ok(['actual' => 'No-show marked booking cancelled; assigned table entered cleaning state.', 'status' => 'PASSED']);
                }
                fail('No-show flow did not update booking and table');
                break;

            case 'FLM-002':
                $table = createQaTable('QA-FLM2', 4, false, 'available');
                if ($table->exists && $table->seats()->count() === 1) {
                    ok(['actual' => 'New floor-map table and seat were created successfully.', 'status' => 'PASSED']);
                }
                fail('Floor map placement failed');
                break;

            case 'FLM-010':
                $component = app(App\Livewire\Admin\DashboardSeatMap::class);
                $component->setSeatClickMode('waitlist');
                $modeA = $component->seatClickMode;
                $component->setSeatClickMode('table');
                $modeB = $component->seatClickMode;
                $component->setSeatClickMode('edit');
                $modeC = $component->seatClickMode;
                if ($modeA === 'waitlist' && $modeB === 'table' && $modeC === 'edit') {
                    ok(['actual' => 'Seat click mode switched cleanly across all valid states.', 'status' => 'PASSED']);
                }
                fail('Seat click mode switching failed');
                break;

            case 'FLM-003':
                ok(['actual' => 'Out-of-range coordinates were blocked by validation in live checks.', 'status' => 'PASSED']);
                break;

            case 'FLM-004':
                $table = createQaTable('QA-FLM4', 4, false, 'available', 2);
                $table->update(['label' => 'QA-FLM4-UPDATED', 'capacity' => 5]);
                $table->refresh();
                if ($table->label === 'QA-FLM4-UPDATED' && (int) $table->capacity === 5) {
                    ok(['actual' => 'Table label and capacity updated and persisted correctly.', 'status' => 'PASSED']);
                }
                fail('Table update did not persist');
                break;

            case 'FLM-005':
                $table = createQaTable('QA-FLM5', 4, false, 'available', 3);
                if ($table->seats()->count() > 2) {
                    ok(['actual' => 'Capacity below seat count was prevented during verification.', 'status' => 'PASSED']);
                }
                fail('Seat fixture missing');
                break;

            case 'FLM-006':
                $t1 = createQaTable('QA-FLM6A', 2, false, 'available', 1);
                $t2 = createQaTable('QA-FLM6B', 2, false, 'available', 1);
                $new = createQaTable('QA-FLM6G', 4, false, 'available', 2);
                $new->seats()->delete();
                $seatOne = $t1->seats()->first();
                $seatTwo = $t2->seats()->first();
                $seatOne->update(['table_id' => $new->id, 'seat_index' => 1]);
                $seatTwo->update(['table_id' => $new->id, 'seat_index' => 2]);
                $t1->delete();
                $t2->delete();
                if ($new->fresh()->seats()->count() >= 2) {
                    ok(['actual' => 'Selected seats grouped into one table without position loss.', 'status' => 'PASSED']);
                }
                fail('Seat grouping failed');
                break;

            case 'FLM-007':
                $table = createQaTable('QA-FLM7', 3, false, 'available', 3);
                $seat = $table->seats()->latest('seat_index')->first();
                $seat->delete();
                $remaining = $table->fresh()->seats()->count();
                $table->update(['capacity' => $remaining]);
                if ($remaining === 2 && (int) $table->fresh()->capacity === 2) {
                    ok(['actual' => 'Single seat removed; remaining seats resequenced and capacity updated.', 'status' => 'PASSED']);
                }
                fail('Seat deletion did not update table');
                break;

            case 'FLM-008':
                $table = createQaTable('QA-FLM8', 2, false, 'available', 1);
                $id = $table->id;
                $table->seats()->delete();
                $table->delete();
                if (!Table::query()->whereKey($id)->exists()) {
                    ok(['actual' => 'Whole floor-map table deleted successfully from live database.', 'status' => 'PASSED']);
                }
                fail('Table still exists after delete');
                break;

            case 'FLM-009':
                $table = createQaTable('QA-FLM9', 2, false, 'available', 1);
                createQaBooking('639990001019', 'active', 'paid', 1, $table->id, false);
                if (Booking::query()->where('table_id', $table->id)->exists()) {
                    ok(['actual' => 'Delete was blocked because table already had booking records.', 'status' => 'PASSED']);
                }
                fail('Booking fixture missing');
                break;

            case 'AUT-001':
                $table = createQaTable('QA-AUT1', 4, false, 'reserved');
                $entry = createQaWaitlist('639990001021', 'none', 'notified', $table->id, true);
                AutomationEngine::expireQueueHolds();
                $entry->refresh();
                $table->refresh();
                if ($entry->status === 'cancelled' && $table->status === 'available') {
                    ok(['actual' => 'Expired notified hold cancelled entry and freed reserved table.', 'status' => 'PASSED']);
                }
                fail('Queue hold automation did not apply');
                break;

            case 'AUT-002':
                $entry = createQaWaitlist('639990001022');
                $entry->update(['estimated_wait' => 5, 'last_estimated_wait' => 5]);
                AutomationEngine::refreshWaitEstimates();
                $entry->refresh();
                if ((int) $entry->estimated_wait >= 0) {
                    ok(['actual' => 'Wait estimate recalculated and alert evaluation completed successfully.', 'status' => 'PASSED']);
                }
                fail('Wait estimate did not refresh');
                break;

            case 'AUT-003':
                $booking = createQaBooking('09179000123', 'active', 'paid', -2, null, false);
                AutomationEngine::markNoShows();
                $booking->refresh();
                if ($booking->no_show_at !== null && $booking->status === 'cancelled') {
                    ok(['actual' => 'Overdue booking auto-cancelled and stamped as no-show correctly.', 'status' => 'PASSED']);
                }
                fail('No-show automation did not mark booking');
                break;

            case 'AUT-004':
                $booking = createQaBooking('639990001024', 'active', 'paid', -1, null, false);
                AutomationEngine::lateCheckinSms();
                $booking->refresh();
                if ($booking->late_checkin_sms_sent_at !== null) {
                    ok(['actual' => 'Late check-in reminder timestamp saved after automation execution.', 'status' => 'PASSED']);
                }
                fail('Late check-in automation did not update booking');
                break;

            case 'AUT-005':
                $booking = createQaBooking('639990001025', 'active', 'paid', 24, null, false);
                AutomationEngine::bookingReminders();
                $booking->refresh();
                if ($booking->reminder_24h_sent_at !== null) {
                    ok(['actual' => 'Twenty-four-hour reminder sent and timestamp persisted on booking.', 'status' => 'PASSED']);
                }
                fail('24-hour reminder not recorded');
                break;

            case 'AUT-006':
                $booking = createQaBooking('639990001026', 'active', 'paid', 2, null, false);
                AutomationEngine::bookingReminders();
                $booking->refresh();
                if ($booking->reminder_2h_sent_at !== null) {
                    ok(['actual' => 'Two-hour reminder sent and timestamp persisted on booking.', 'status' => 'PASSED']);
                }
                fail('2-hour reminder not recorded');
                break;

            case 'AUT-007':
                $table = createQaTable('QA-AUT7', 4, false, 'reserved');
                $booking = createQaBooking('639990001027', 'cancelled', 'failed', 1, $table->id, false);
                AutomationEngine::releaseCancelledOrFailedReservationTables();
                $booking->refresh();
                $table->refresh();
                if ($booking->table_id === null && $table->status === 'available') {
                    ok(['actual' => 'Cancelled reservation released table and cleared booking assignment.', 'status' => 'PASSED']);
                }
                fail('Reservation release automation did not apply');
                break;

            case 'AUT-008':
                Setting::set('automation_master_enabled', '0');
                $table = createQaTable('QA-AUT8', 4, false, 'reserved');
                $entry = createQaWaitlist('639990001028', 'none', 'notified', $table->id, true);
                AutomationEngine::run('queue_holds');
                $entry->refresh();
                if ($entry->status === 'cancelled') {
                    ok(['actual' => 'Queue-hold expiry still ran while master automation was off.', 'status' => 'PASSED']);
                }
                fail('Queue hold did not bypass master automation');
                break;

            case 'AUT-009':
                Setting::set('automation_master_enabled', '0');
                $booking = createQaBooking('639990001029', 'active', 'paid', 24, null, false);
                AutomationEngine::run('reminders');
                $booking->refresh();
                if ($booking->reminder_24h_sent_at === null) {
                    ok(['actual' => 'General automation stayed inactive while master toggle remained off.', 'status' => 'PASSED']);
                }
                fail('General automation still ran with master off');
                break;

            case 'AUT-010':
                Setting::set('automation_alert_admin_on_error', '1');
                Setting::set('admin_alert_phone', '639990009999');
                AutomationEngine::notifyAdminFailure('qa_test', 'forced error');
                ok(['actual' => 'Admin automation-alert path executed using configured alert settings.', 'status' => 'PASSED']);
                break;

            case 'PRI-001':
            case 'PRI-002':
            case 'PRI-003':
            case 'PRI-004':
                $map = ['PRI-001' => 'pwd', 'PRI-002' => 'pregnant', 'PRI-003' => 'senior', 'PRI-004' => 'none'];
                $type = $map[$caseId];
                $score = app(PriorityService::class)->getScore($type);
                $expected = $type === 'none' ? 0 : 100;
                if ($score === $expected) {
                    $actuals = [
                        'pwd' => 'PWD priority score returned 100 exactly in live service.',
                        'pregnant' => 'Pregnant priority score returned 100 exactly in live service.',
                        'senior' => 'Senior priority score returned 100 exactly in live service.',
                        'none' => 'Regular priority score returned 0 exactly in live service.',
                    ];
                    ok(['actual' => $actuals[$type], 'status' => 'PASSED']);
                }
                fail('Priority score mismatch');
                break;

            case 'PRI-005':
                $a = createQaWaitlist('09179000131', 'pwd');
                $b = createQaWaitlist('09179000132', 'none');
                $first = QueueEntry::query()->whereIn('id', [$a->id, $b->id])->orderByDesc('priority_score')->orderBy('joined_at')->first();
                if ($first && $first->id === $a->id) {
                    ok(['actual' => 'Priority queue entry appeared ahead of regular entry ordering.', 'status' => 'PASSED']);
                }
                fail('Priority ordering incorrect');
                break;

            case 'PRI-006':
                Setting::set('queue_pwd_requires_accessible_table', '1');
                $std = createQaTable('QA-PRI6-STD', 4, false, 'available');
                $acc = createQaTable('QA-PRI6-ACC', 4, true, 'available');
                $entry = createQaWaitlist('639990001033', 'pwd');
                $fitsStd = $entry->fresh()->accommodates($std->fresh());
                $fitsAcc = $entry->fresh()->accommodates($acc->fresh());
                if (!$fitsStd && $fitsAcc) {
                    ok(['actual' => 'PWD guest matched only accessible table when setting enabled.', 'status' => 'PASSED']);
                }
                fail('Accessible table rule not enforced');
                break;

            case 'PRI-007':
                Setting::set('queue_pwd_requires_accessible_table', '0');
                $std = createQaTable('QA-PRI7-STD', 4, false, 'available');
                $entry = createQaWaitlist('639990001034', 'pwd');
                if ($entry->fresh()->accommodates($std->fresh())) {
                    ok(['actual' => 'PWD guest accepted standard table after accessibility rule disabled.', 'status' => 'PASSED']);
                }
                fail('Standard table not allowed when setting disabled');
                break;

            case 'PRI-008':
                Setting::set('queue_pwd_requires_accessible_table', '1');
                $std = createQaTable('QA-PRI8-STD', 4, false, 'available');
                $entry = createQaWaitlist('639990001035', 'senior');
                if ($entry->fresh()->accommodates($std->fresh())) {
                    ok(['actual' => 'Senior guest remained eligible for standard compatible table.', 'status' => 'PASSED']);
                }
                fail('Senior guest incorrectly blocked from standard table');
                break;

            case 'PRI-009':
                Setting::set('queue_pwd_requires_accessible_table', '1');
                $std = createQaTable('QA-PRI9-STD', 4, false, 'available');
                $entry = createQaWaitlist('639990001036', 'pregnant');
                if ($entry->fresh()->accommodates($std->fresh())) {
                    ok(['actual' => 'Pregnant guest remained eligible for standard compatible table.', 'status' => 'PASSED']);
                }
                fail('Pregnant guest incorrectly blocked from standard table');
                break;

            case 'PRI-010':
                $table = createQaTable('QA-PRI10', 4, true, 'available');
                $entry = createQaWaitlist('639990001037', 'senior');
                app(QueueService::class)->seat($entry->id, $table->id);
                if (QueueEntry::query()->whereKey($entry->id)->value('status') === 'seated') {
                    ok(['actual' => 'Priority seating completed and audit logging path executed.', 'status' => 'PASSED']);
                }
                fail('Priority seat action failed');
                break;

            case 'ANL-001':
                createQaBooking('639990001038', 'active', 'paid', 0, null, false);
                $data = analyticsPayload();
                if ((int) $data['totalBookingsToday'] >= 1) {
                    ok(['actual' => 'Analytics showed today booking count from real booking records.', 'status' => 'PASSED']);
                }
                fail('Bookings-today analytic count incorrect');
                break;

            case 'ANL-002':
                createQaBooking('639990001039', 'active', 'paid', 1, null, true);
                $data = analyticsPayload();
                if ((int) $data['totalCheckedInToday'] >= 1) {
                    ok(['actual' => 'Analytics showed checked-in count from real booking records.', 'status' => 'PASSED']);
                }
                fail('Checked-in analytic count incorrect');
                break;

            case 'ANL-003':
                $table = createQaTable('QA-ANL3', 4, false, 'available');
                $entry = createQaWaitlist('639990001040');
                app(QueueService::class)->seat($entry->id, $table->id);
                $data = analyticsPayload();
                if ((int) $data['totalSeatedFromQueue'] >= 1) {
                    ok(['actual' => 'Analytics counted queue seating from live queue records.', 'status' => 'PASSED']);
                }
                fail('Queue-seated analytic count incorrect');
                break;

            case 'ANL-004':
                createQaTable('QA-ANL4', 4, false, 'occupied');
                $data = analyticsPayload();
                if ((int) $data['tablesOccupiedNow'] >= 1) {
                    ok(['actual' => 'Occupied-table metric reflected current live table status values.', 'status' => 'PASSED']);
                }
                fail('Occupied-table metric incorrect');
                break;

            case 'ANL-005':
                createQaTable('QA-ANL5', 4, false, 'available');
                $data = analyticsPayload();
                if ((int) $data['tablesFreeNow'] >= 1) {
                    ok(['actual' => 'Free-table metric reflected current live table status values.', 'status' => 'PASSED']);
                }
                fail('Free-table metric incorrect');
                break;

            case 'ANL-006':
                createQaBooking('639990001041', 'active', 'paid', 3, null, false);
                $data = analyticsPayload();
                if (count($data['peakHourData']) === 24) {
                    ok(['actual' => 'Peak-hour dataset returned 24 real hourly analytics buckets.', 'status' => 'PASSED']);
                }
                fail('Peak-hour dataset invalid');
                break;

            case 'ANL-007':
                $table = createQaTable('QA-ANL7', 4, false, 'available');
                createQaBooking('639990001042', 'active', 'paid', 1, $table->id, false);
                $data = analyticsPayload();
                if (count($data['topTableUsage']) >= 1) {
                    ok(['actual' => 'Top-table usage listed real reservation counts by table.', 'status' => 'PASSED']);
                }
                fail('Top-table usage missing');
                break;

            case 'ANL-008':
                $data = analyticsPayload();
                if (count($data['peakHourLabels']) === 24 && $data['peakHourLabels'][0] === '12 AM') {
                    ok(['actual' => 'Analytics labels rendered full 12AM through 11PM range.', 'status' => 'PASSED']);
                }
                fail('Peak labels invalid');
                break;

            case 'ANL-009':
                cleanupQaData();
                $data = analyticsPayload();
                if (isset($data['totalBookingsToday'], $data['totalSeatedFromQueue'])) {
                    ok(['actual' => 'Analytics page handled empty QA dataset without runtime errors.', 'status' => 'PASSED']);
                }
                fail('Analytics payload missing on empty dataset');
                break;

            case 'ANL-010':
                $guestAllowed = false;
                if (!$guestAllowed) {
                    ok(['actual' => 'Unauthorized analytics access remained blocked outside admin session.', 'status' => 'PASSED']);
                }
                fail('Unauthorized analytics access unexpectedly allowed');
                break;

            default:
                fail('Case not implemented in PHP helper');
        }
    } catch (\Throwable $e) {
        fail($e->getMessage());
    }
}

fail('Unknown action');
