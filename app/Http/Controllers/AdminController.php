<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\SeatingLayoutController;
use App\Models\AutomationLog;
use App\Models\AdminLog;
use App\Models\Booking;
use App\Models\QueueEntry;
use App\Models\Setting;
use App\Models\SmsLog;
use App\Models\Table;
use App\Services\BookingConfirmationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

/**
 * AdminController
 *
 * Serves the admin dashboard and management pages.
 * Dashboard functionality is handled by Livewire components.
 */
class AdminController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function index()
    {
        return view('admin.dashboard');
    }

    /**
     * Seating analytics (charts and metrics).
     */
    public function seatingAnalytics()
    {
        return view('admin.seating-analytics');
    }

    /**
     * Read-only operational log summary for administrators.
     */
    public function systemLogs()
    {
        return view('admin.system-logs', [
            'sections' => [
                [
                    'title' => 'Recent Waitlist Changes',
                    'description' => 'Latest queue entries and hold/seating state from the waitlist workflow.',
                    'rows' => $this->recentWaitlistLogRows(),
                ],
                [
                    'title' => 'Recent SMS Logs',
                    'description' => 'Semaphore/text-message attempts created by queue, booking, and automation actions.',
                    'rows' => $this->recentSmsLogRows(),
                ],
                [
                    'title' => 'Automation Logs',
                    'description' => 'View system-triggered actions such as reminders, no-shows, SMS attempts, and table releases.',
                    'type' => 'automation',
                    'note' => 'Automation runs through Laravel scheduler. This page shows recorded system actions after they occur.',
                    'rows' => $this->recentAutomationLogRows(),
                ],
                [
                    'title' => 'Recent Priority Actions',
                    'description' => 'Priority waitlist entries for PWD, pregnant, senior, and regular queue handling.',
                    'rows' => $this->recentPriorityLogRows(),
                ],
                [
                    'title' => 'Recent Table Status Changes',
                    'description' => 'Current table states used by table management and seating availability.',
                    'rows' => $this->recentTableLogRows(),
                ],
                [
                    'title' => 'Recent Analytics Source Records',
                    'description' => 'Bookings, seated queue entries, and table status records behind reports and charts.',
                    'rows' => $this->recentAnalyticsSourceLogRows(),
                ],
            ],
            'summary' => [
                'waitlist' => QueueEntry::count(),
                'sms' => SmsLog::count(),
                'automation' => AutomationLog::count(),
                'priority' => QueueEntry::where('priority_score', '>', 0)->count(),
                'tables' => Table::count(),
                'bookingsToday' => Booking::whereDate('booked_at', today())->count(),
            ],
        ]);
    }

    /**
     * Display the tables management page.
     */
    public function tables(Request $request)
    {
        $tableIds = Table::query()->pluck('id')->all();

        return view('admin.tables', array_merge(
            SeatingLayoutController::layoutData(),
            [
                'dailyMergeGroups' => SeatingLayoutController::plannerMergeGroups($tableIds),
            ],
            $this->floorMapOperationsData($request),
        ));
    }

    private function floorMapOperationsData(Request $request): array
    {
        $tz = (string) config('app.timezone');
        $date = (string) $request->query('date', now($tz)->toDateString());

        try {
            $calendarDate = Carbon::parse($date, $tz)->startOfDay();
        } catch (\Throwable) {
            $calendarDate = now($tz)->startOfDay();
        }

        $calendarBookings = Booking::query()
            ->with('table')
            ->whereDate('booked_at', $calendarDate->toDateString())
            ->orderBy('booked_at')
            ->orderBy('id')
            ->get();

        $waitingGuests = QueueEntry::query()
            ->whereIn('status', ['waiting', 'notified'])
            ->orderByDesc('priority_score')
            ->orderBy('joined_at')
            ->take(12)
            ->get();

        $pendingBookings = Booking::query()
            ->with('table')
            ->whereIn('status', ['active', 'pending'])
            ->where(function ($query) {
                $query->whereNull('table_id')
                    ->orWhereIn('payment_status', ['pending', 'pending_verification']);
            })
            ->orderBy('booked_at')
            ->take(12)
            ->get();

        $floorMapActiveBookingsByTable = Booking::query()
            ->whereNotNull('table_id')
            ->whereIn('status', ['active', 'pending'])
            ->whereNull('no_show_at')
            ->orderByDesc('id')
            ->get()
            ->unique('table_id')
            ->keyBy('table_id');

        $calendarSlots = collect(range(10, 21))
            ->map(fn (int $hour) => sprintf('%02d:00', $hour))
            ->all();

        return [
            'calendarDate' => $calendarDate,
            'calendarSlots' => $calendarSlots,
            'calendarBookings' => $calendarBookings,
            'floorMapActiveBookingsByTable' => $floorMapActiveBookingsByTable,
            'waitingGuests' => $waitingGuests,
            'todaysReservations' => $calendarBookings,
            'pendingBookings' => $pendingBookings,
        ];
    }

    /**
     * Display the waitlist management page.
     */
    public function waitlist()
    {
        return view('admin.waitlist');
    }

    /**
     * Display the bookings history page.
     */
    public function bookings()
    {
        return view('admin.bookings', [
            'recentBookings' => Booking::with('table')->latest()->take(50)->get(),
        ]);
    }

    public function uploadQr(Request $request): RedirectResponse
    {
        $request->validate([
            'qr_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $dir = public_path('images');
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $request->file('qr_image')->move($dir, 'qrcode-temp.png');

        return back()->with('success', 'Image uploaded. Now crop the QR code area.');
    }

    public function saveQrCrop(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'qr_account_name' => ['required', 'string', 'max:100'],
            'qr_account_number' => ['required', 'string', 'max:50'],
            'qr_payment_label' => ['nullable', 'string', 'max:100'],
            'crop_x' => ['required', 'numeric'],
            'crop_y' => ['required', 'numeric'],
            'crop_width' => ['required', 'numeric'],
            'crop_height' => ['required', 'numeric'],
        ]);

        $tempPath = public_path('images/qrcode-temp.png');
        if (!File::isFile($tempPath)) {
            return back()->withErrors(['crop_x' => 'No uploaded image found. Please upload a screenshot first.'])->withInput();
        }

        $manager = ImageManager::gd();
        $image = $manager->read($tempPath);

        $iw = $image->width();
        $ih = $image->height();

        $x = (int) round((float) $validated['crop_x']);
        $y = (int) round((float) $validated['crop_y']);
        $cw = (int) round((float) $validated['crop_width']);
        $ch = (int) round((float) $validated['crop_height']);

        $x = max(0, min($x, max(0, $iw - 1)));
        $y = max(0, min($y, max(0, $ih - 1)));
        $cw = max(1, min($cw, $iw - $x));
        $ch = max(1, min($ch, $ih - $y));

        $image->crop($cw, $ch, $x, $y);
        $image->cover(400, 400);

        $outPath = public_path('images/qrcode.png');
        $image->save($outPath);

        File::delete($tempPath);

        Setting::set('qr_image_path', 'images/qrcode.png');
        Setting::set('qr_account_name', $validated['qr_account_name']);
        Setting::set('qr_account_number', $validated['qr_account_number']);
        Setting::set(
            'qr_payment_label',
            $validated['qr_payment_label'] ?? 'GCash · InstaPay accepted'
        );
        Setting::set('qr_updated_at', now()->timestamp);

        return back()->with('success', 'QR code updated successfully.');
    }

    public function verifyPayment(Booking $booking): RedirectResponse
    {
        if ($booking->payment_method === 'paymongo' || $booking->paymongo_payment_id !== null) {
            return back()->with('error', 'PayMongo payments are confirmed automatically — they cannot be approved here.');
        }

        if ($booking->payment_method !== 'manual_qr' || $booking->payment_status !== 'pending_verification') {
            return back()->with('error', 'Only manual QR bookings awaiting verification can be approved with this action.');
        }

        $verifierId = auth()->id();
        if ($verifierId === null) {
            return back()->with('error', 'You must be signed in to verify payments.');
        }

        $booking->update([
            'payment_verified_by' => (string) $verifierId,
        ]);

        $table = app(BookingConfirmationService::class)->confirm($booking);

        if ($table) {
            return back()->with(
                'success',
                'Payment verified. Table ' . $table->label . ' assigned automatically.'
            );
        }

        return back()->with(
            'success',
            'Payment verified. No table available — please assign manually.'
        );
    }

    public function rejectPayment(Booking $booking): RedirectResponse
    {
        if ($booking->payment_method !== 'manual_qr') {
            return back()->with('error', 'Only manual QR bookings can be rejected with this action.');
        }

        if ($booking->payment_status !== 'pending_verification') {
            return back()->with('error', 'This booking is not awaiting payment verification.');
        }

        if (auth()->id() === null) {
            return back()->with('error', 'You must be signed in to reject payments.');
        }

        app(BookingConfirmationService::class)->reject($booking);

        return back()->with('success', 'Payment rejected. The guest has been notified by SMS.');
    }

    public function menu()
    {
        if (auth()->check() && !auth()->user()->isAdmin()) {
            abort(403);
        }

        return view('admin.menu');
    }

    public function settings()
    {
        if (auth()->check() && !auth()->user()->isAdmin()) {
            abort(403);
        }

        return view('admin.settings');
    }

    public function logs()
    {
        if (auth()->check() && !auth()->user()->isAdmin()) {
            abort(403);
        }

        return view('admin.logs');
    }

    private function recentWaitlistLogRows()
    {
        return QueueEntry::query()
            ->latest('updated_at')
            ->latest('id')
            ->take(12)
            ->get()
            ->map(function (QueueEntry $entry) {
                $time = $this->latestLogTime(
                    $entry->updated_at,
                    $entry->seated_at,
                    $entry->notified_at,
                    $entry->joined_at
                );

                $action = match ($entry->status) {
                    'notified' => 'Table-ready hold created',
                    'seated' => 'Guest seated from waitlist',
                    'cancelled' => 'Waitlist entry cancelled',
                    default => 'Walk-in joined waitlist',
                };

                return [
                    'time' => $time,
                    'action' => $action,
                    'related' => $this->guestLabel($entry->customer_name, $entry->party_size, $entry->queue_display_number),
                    'status' => $this->compactParts([
                        'Status: '.$entry->status,
                        $entry->estimated_wait !== null ? 'ETA '.$entry->estimated_wait.' min' : null,
                        $entry->reserved_table_id ? 'Table #'.$entry->reserved_table_id : null,
                        $entry->hold_expires_at ? 'Hold until '.$entry->hold_expires_at->format('g:i A') : null,
                    ]),
                    'source' => 'queue_entries #'.$entry->id,
                ];
            });
    }

    private function recentSmsLogRows()
    {
        return SmsLog::query()
            ->latest('created_at')
            ->latest('id')
            ->take(12)
            ->get()
            ->map(fn (SmsLog $log) => [
                'time' => $log->created_at,
                'action' => Str::headline((string) ($log->template ?: 'sms')),
                'related' => $this->contextSummary($log->context, $log->phone ?: 'No phone stored'),
                'status' => $this->compactParts([
                    'Status: '.($log->status ?: 'unknown'),
                    $log->semaphore_message_id ? 'Semaphore ID '.$log->semaphore_message_id : null,
                    $log->error_message ? 'Error: '.$log->error_message : null,
                ]),
                'source' => 'sms_logs #'.$log->id,
            ]);
    }

    private function recentAutomationLogRows()
    {
        $logs = AutomationLog::query()
            ->latest('created_at')
            ->latest('id')
            ->take(12)
            ->get();

        $bookingIds = $logs
            ->map(fn (AutomationLog $log) => data_get($log->payload, 'booking_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $entryIds = $logs
            ->map(fn (AutomationLog $log) => data_get($log->payload, 'entry_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $tableIds = $logs
            ->map(fn (AutomationLog $log) => data_get($log->payload, 'table_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $bookings = $bookingIds->isEmpty()
            ? collect()
            : Booking::query()->whereIn('id', $bookingIds)->get()->keyBy('id');

        $entries = $entryIds->isEmpty()
            ? collect()
            : QueueEntry::query()->whereIn('id', $entryIds)->get()->keyBy('id');

        $tables = $tableIds->isEmpty()
            ? collect()
            : Table::query()->whereIn('id', $tableIds)->get()->keyBy('id');

        return $logs
            ->map(function (AutomationLog $log) use ($bookings, $entries, $tables) {
                $payload = $log->payload ?? [];
                $booking = ($bookingId = data_get($payload, 'booking_id')) ? $bookings->get((int) $bookingId) : null;
                $entry = ($entryId = data_get($payload, 'entry_id')) ? $entries->get((int) $entryId) : null;
                $table = ($tableId = data_get($payload, 'table_id')) ? $tables->get((int) $tableId) : null;
                $sms = $this->automationSmsLog($log, $booking, $entry);
                $statusText = $log->success ? 'Completed' : 'Failed';
                $affected = $this->automationAffectedRecord($log, $booking, $entry, $table);
                $message = $log->message ?: ($log->success ? 'Completed' : 'Failed');

                return [
                    'time' => $log->created_at,
                    'task' => $this->automationTaskLabel((string) $log->task),
                    'task_key' => (string) $log->task,
                    'status_text' => $statusText,
                    'status_badge' => $log->success ? 'completed' : 'failed',
                    'affected' => $affected,
                    'message' => $message,
                    'sms_result' => $sms['text'],
                    'sms_status' => $sms['status'],
                    'action' => $this->automationTaskLabel((string) $log->task),
                    'related' => $affected,
                    'status' => $this->compactParts([
                        $statusText,
                        $message,
                        $sms['text'],
                    ]),
                    'source' => 'automation_logs #'.$log->id,
                ];
            });
    }

    private function automationTaskLabel(string $task): string
    {
        return match ($task) {
            'queue_holds' => 'Queue Hold Expiry',
            'wait_estimates' => 'Wait Estimate Alerts',
            'no_shows' => 'No-show Automation',
            'late_checkin' => 'Late Check-in SMS',
            'reminders' => 'Reservation Reminders',
            'reservation_table_release' => 'Reservation Table Release',
            default => Str::headline($task),
        };
    }

    private function automationAffectedRecord(
        AutomationLog $log,
        ?Booking $booking,
        ?QueueEntry $entry,
        ?Table $table
    ): string {
        $payload = $log->payload ?? [];

        if ($booking) {
            return $this->compactParts([
                'Booking '.$booking->booking_ref,
                $this->guestLabel($booking->customer_name, $booking->party_size),
                $booking->table_id ? 'Table #'.$booking->table_id : null,
            ]);
        }

        if ($entry) {
            return $this->compactParts([
                'Queue entry #'.$entry->id,
                $this->guestLabel($entry->customer_name, $entry->party_size, $entry->queue_display_number),
                $entry->reserved_table_id ? 'Table #'.$entry->reserved_table_id : null,
            ]);
        }

        if ($table) {
            return $this->tableLabel($table);
        }

        if (isset($payload['table_label']) && is_scalar($payload['table_label'])) {
            return 'Table '.$payload['table_label'];
        }

        if (isset($payload['exception']) && is_scalar($payload['exception'])) {
            return 'Exception: '.$payload['exception'];
        }

        return $this->contextSummary($payload, 'Automation task');
    }

    private function automationSmsLog(AutomationLog $log, ?Booking $booking, ?QueueEntry $entry): array
    {
        $templates = $this->automationSmsTemplates($log);

        if ($templates === []) {
            return [
                'status' => 'standard',
                'text' => 'No SMS required',
            ];
        }

        $time = $log->created_at ?? now();
        $candidates = SmsLog::query()
            ->whereIn('template', $templates)
            ->where('created_at', '>=', $time->copy()->subMinutes(10))
            ->where('created_at', '<=', $time->copy()->addHours(2))
            ->latest('created_at')
            ->latest('id')
            ->take(30)
            ->get();

        $matched = $candidates
            ->first(fn (SmsLog $sms) => $this->smsMatchesAutomation($sms, $log, $booking, $entry))
            ?? $candidates->first();

        if (! $matched) {
            return [
                'status' => 'pending',
                'text' => 'SMS expected; no SMS log yet',
            ];
        }

        return [
            'status' => $matched->status ?: 'pending',
            'text' => $this->compactParts([
                'SMS '.($matched->status ?: 'unknown'),
                'Template: '.Str::headline((string) $matched->template),
                $matched->semaphore_message_id ? 'Semaphore ID '.$matched->semaphore_message_id : null,
                $matched->error_message ? 'Error: '.$matched->error_message : null,
                'sms_logs #'.$matched->id,
            ]),
        ];
    }

    private function automationSmsTemplates(AutomationLog $log): array
    {
        $message = strtolower((string) $log->message);

        if (! $log->success) {
            return ['automation_error'];
        }

        return match ((string) $log->task) {
            'queue_holds' => ['queue_skipped'],
            'wait_estimates' => ['wait_extended'],
            'no_shows' => ['no_show'],
            'late_checkin' => ['late_checkin'],
            'reminders' => str_contains($message, '24h')
                ? ['reminder_24h']
                : (str_contains($message, '2h') ? ['reminder_2h'] : ['reminder_24h', 'reminder_2h']),
            default => [],
        };
    }

    private function smsMatchesAutomation(SmsLog $sms, AutomationLog $log, ?Booking $booking, ?QueueEntry $entry): bool
    {
        $context = $sms->context ?? [];

        if (! $log->success) {
            return (string) data_get($context, 'task') === (string) $log->task;
        }

        if ($booking) {
            $ref = (string) $booking->booking_ref;

            return in_array($ref, [
                (string) data_get($context, 'ref'),
                (string) data_get($context, 'booking_ref'),
            ], true);
        }

        if ($entry) {
            $name = trim((string) $entry->customer_name);

            return $name !== '' && trim((string) data_get($context, 'name')) === $name;
        }

        return true;
    }

    private function recentPriorityLogRows()
    {
        $queueRows = QueueEntry::query()
            ->where('priority_score', '>', 0)
            ->latest('updated_at')
            ->latest('id')
            ->take(12)
            ->get()
            ->map(fn (QueueEntry $entry) => [
                'time' => $this->latestLogTime($entry->updated_at, $entry->seated_at, $entry->joined_at),
                'action' => Str::headline($entry->priority_type).' priority queue action',
                'related' => $this->guestLabel($entry->customer_name, $entry->party_size, $entry->queue_display_number),
                'status' => $this->compactParts([
                    'Score '.$entry->priority_score,
                    $entry->needs_accessible ? 'Accessible table required' : 'Standard table allowed',
                    'Status: '.$entry->status,
                ]),
                'source' => 'queue_entries #'.$entry->id,
            ])
            ->toBase();

        $auditRows = AdminLog::query()
            ->where('action', 'priority_seating')
            ->latest('created_at')
            ->latest('id')
            ->take(12)
            ->get()
            ->map(fn (AdminLog $log) => [
                'time' => $log->created_at,
                'action' => 'Priority seating action',
                'related' => $log->target_id ? 'Queue entry #'.$log->target_id : 'Priority queue action',
                'status' => $log->details ?: 'Priority guest seated',
                'source' => 'admin_logs #'.$log->id,
            ])
            ->toBase();

        return $queueRows
            ->merge($auditRows)
            ->sortByDesc(fn (array $row) => optional($row['time'])->timestamp ?? 0)
            ->take(12)
            ->values();
    }

    private function recentTableLogRows()
    {
        return Table::query()
            ->withCount('seats')
            ->latest('updated_at')
            ->latest('id')
            ->take(12)
            ->get()
            ->map(function (Table $table) {
                $seatCount = (int) $table->seats_count;

                return [
                    'time' => $this->latestLogTime($table->updated_at, $table->occupied_at, $table->cleaning_started_at),
                    'action' => 'Table status is '.Str::headline((string) $table->status),
                    'related' => $this->tableLabel($table),
                    'status' => $this->compactParts([
                        'Capacity '.$table->capacity,
                        $seatCount.' mapped seat'.($seatCount === 1 ? '' : 's'),
                        $table->booking_id ? 'Booking #'.$table->booking_id : null,
                        $table->occupied_party ? 'Party '.$table->occupied_party : null,
                    ]),
                    'source' => 'tables #'.$table->id,
                ];
            });
    }

    private function recentAnalyticsSourceLogRows()
    {
        $bookings = Booking::query()
            ->with('table')
            ->latest('updated_at')
            ->latest('id')
            ->take(6)
            ->get()
            ->map(fn (Booking $booking) => [
                'time' => $this->latestLogTime(
                    $booking->updated_at,
                    $booking->checked_in_at,
                    $booking->no_show_at,
                    $booking->booked_at
                ),
                'action' => 'Booking analytics source',
                'related' => $this->compactParts([
                    $booking->booking_ref,
                    $this->guestLabel($booking->customer_name, $booking->party_size),
                    $booking->table ? $this->tableLabel($booking->table) : null,
                ]),
                'status' => $this->compactParts([
                    'Booking: '.$booking->status,
                    'Payment: '.($booking->payment_status ?: 'unknown'),
                    $booking->checked_in_at ? 'Checked in' : null,
                    $booking->no_show_at ? 'No-show' : null,
                ]),
                'source' => 'bookings #'.$booking->id,
            ])
            ->toBase();

        $seatedQueue = QueueEntry::query()
            ->whereNotNull('seated_at')
            ->latest('seated_at')
            ->latest('id')
            ->take(6)
            ->get()
            ->map(fn (QueueEntry $entry) => [
                'time' => $entry->seated_at,
                'action' => 'Queue seated analytics source',
                'related' => $this->guestLabel($entry->customer_name, $entry->party_size, $entry->queue_display_number),
                'status' => $this->compactParts([
                    'Status: '.$entry->status,
                    $entry->reserved_table_id ? 'Table #'.$entry->reserved_table_id : null,
                ]),
                'source' => 'queue_entries #'.$entry->id,
            ])
            ->toBase();

        $tables = Table::query()
            ->latest('updated_at')
            ->latest('id')
            ->take(6)
            ->get()
            ->map(fn (Table $table) => [
                'time' => $this->latestLogTime($table->updated_at, $table->occupied_at, $table->cleaning_started_at),
                'action' => 'Table count analytics source',
                'related' => $this->tableLabel($table),
                'status' => 'Current status: '.$table->status,
                'source' => 'tables #'.$table->id,
            ])
            ->toBase();

        return $bookings
            ->merge($seatedQueue)
            ->merge($tables)
            ->sortByDesc(fn (array $row) => optional($row['time'])->timestamp ?? 0)
            ->take(12)
            ->values();
    }

    private function latestLogTime(...$times)
    {
        return collect($times)
            ->filter()
            ->sortByDesc(fn ($time) => $time->timestamp)
            ->first();
    }

    private function contextSummary(?array $context, string $fallback): string
    {
        if (! $context) {
            return $fallback;
        }

        $keys = [
            'customer_name',
            'guest',
            'name',
            'booking_ref',
            'queue_number',
            'table_label',
            'table',
            'party_size',
            'task',
            'entry_id',
            'queue_entry_id',
            'booking_id',
            'table_id',
        ];

        $parts = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $context) && is_scalar($context[$key])) {
                $parts[] = Str::headline($key).': '.$context[$key];
            }
        }

        if ($parts === []) {
            foreach ($context as $key => $value) {
                if (is_scalar($value)) {
                    $parts[] = Str::headline((string) $key).': '.$value;
                }

                if (count($parts) >= 3) {
                    break;
                }
            }
        }

        return $parts === [] ? $fallback : implode(' / ', $parts);
    }

    private function guestLabel(?string $name, ?int $partySize, ?int $queueNumber = null): string
    {
        return $this->compactParts([
            $queueNumber ? 'Ticket #'.$queueNumber : null,
            $name ?: 'Guest',
            $partySize ? $partySize.' guest'.($partySize === 1 ? '' : 's') : null,
        ]);
    }

    private function tableLabel(Table $table): string
    {
        return $table->label.' (Table #'.$table->id.')';
    }

    private function compactParts(array $parts): string
    {
        return collect($parts)
            ->filter(fn ($part) => $part !== null && $part !== '')
            ->implode(' | ');
    }
}
