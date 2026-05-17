<?php

namespace App\Livewire;

use App\Models\Booking;
use App\Models\Setting;
use App\Models\Table;
use App\Rules\PhilippinePhone;
use App\Services\BookingGuardService;
use App\Services\PayMongoService;
use App\Support\DeviceContext;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ReservationForm extends Component
{
    public string $name = '';
    public string $phone = '';
    public string $email = '';
    public $guests = 2;
    public string $date = '';
    public string $time = '';

    /** @var array<string, list<array{time: string, available: bool}>> */
    public array $availableSlots = [];

    /**
     * Breakfast / Lunch / Tea Time / Dinner — each has slots list and whether the category applies today.
     *
     * @var array<string, array{inactive: bool, slots: list<array{time: string, available: bool}>}>
     */
    public array $slotsByCategory = [];

    public bool $slotModalNoAvailabilityAll = false;

    public string $selectedSlot = '';

    public bool $showSlotModal = false;

    // Honeypot — bots fill this, humans leave it empty
    public string $website = '';

    public int $step = 0;

    /** Form micro-step within {@see $step} 1: 1 = contact & slot, 2 = deposit, policy, special requests & submit (synced to Alpine for the step indicator). */
    public int $subStep = 1;

    public bool $success = false;

    /** Set when {@see submit()} succeeds; drives success UI (e.g. manual_qr awaiting verification). */
    public ?string $successPaymentMethod = null;

    public string $bookingRef = '';
    public string $errorMessage = '';
    public bool $processing = false;

    public string $transactionNumber = '';

    public bool $policyAcknowledged = false;

    public string $specialRequests = '';

    /** When PayMongo is configured: `online` = PayMongo checkout; `manual` = QR + reference, {@see submit()}. */
    public string $reservationPaymentMode = 'online';

    private const ALLOWED_TIMES = [
        '11:00',
        '11:30',
        '12:00',
        '12:30',
        '13:00',
        '13:30',
        '14:00',
        '14:30',
        '15:00',
        '15:30',
        '16:00',
        '16:30',
        '17:00',
        '17:30',
        '18:00',
        '18:30',
        '19:00',
        '19:30',
        '20:00',
        '20:30',
        '21:00',
        '21:30',
        '22:00',
    ];

    /**
     * Same values persisted on {@see Booking::$payment_method}: reference is only for `manual_qr`.
     */
    private function effectivePaymentMethod(): string
    {
        if (!$this->paymongoEnabled) {
            return 'manual_qr';
        }

        return $this->reservationPaymentMode === 'manual' ? 'manual_qr' : 'paymongo';
    }

    protected function rules(): array
    {
        $maxParty = $this->maxPartySizeForReservation();

        $transactionRules = $this->effectivePaymentMethod() === 'manual_qr'
            ? ['required', 'string', 'digits_between:10,255']
            : ['nullable', 'string'];

        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[\pL\s\-\'.]+$/u'],
            'phone' => ['required', new PhilippinePhone],
            'email' => 'required|email:rfc,dns|max:255',
            'guests' => ['required', 'integer', 'min:1', 'max:' . $maxParty],
            'date' => 'required|date|after_or_equal:today|before_or_equal:' . now()->addMonths(3)->toDateString(),
            'time' => ['required', 'string', Rule::in(self::slotTimeWhitelist())],
            'transactionNumber' => $transactionRules,
            'policyAcknowledged' => ['accepted'],
            'specialRequests' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected array $messages = [
        'name.regex' => 'Name may only contain letters, spaces, hyphens, and apostrophes.',
        'date.before_or_equal' => 'Reservations can only be made up to 3 months in advance.',
        'time.in' => 'Please select a valid reservation time between 11:00 and 22:00.',
        'policyAcknowledged.accepted' => 'Please confirm that you understand the reservation policies.',
        'guests.min' => 'Party size must be at least :min.',
        'guests.max' => 'Party size cannot exceed :max (largest table capacity).',
        'transactionNumber.required' => 'Please enter your payment reference number.',
        'transactionNumber.digits_between' => 'Reference must be numbers only, at least 10 digits.',
    ];

    public function updated(string $property): void
    {
        if ($property === 'subStep' || $property === 'transactionNumber' || $property === 'reservationPaymentMode') {
            if ($property === 'reservationPaymentMode') {
                $this->resetValidation(['transactionNumber']);
            }

            return;
        }
        $this->validateOnly($property);
    }

    public function updatedTransactionNumber(): void
    {
        $this->transactionNumber = preg_replace('/\D/', '', (string) $this->transactionNumber);
        if ($this->effectivePaymentMethod() === 'manual_qr') {
            $this->validateOnly('transactionNumber');
        }
    }

    public function confirmPolicy(): void
    {
        $this->step = 1;
        $this->subStep = 1;
    }

    public function validateStepOneA(): void
    {
        $this->resetValidation(['name', 'phone', 'email', 'guests', 'date', 'time']);

        $maxParty = $this->maxPartySizeForReservation();

        $validator = Validator::make(
            [
                'name' => $this->name,
                'phone' => $this->phone,
                'email' => $this->email,
                'guests' => $this->guests,
                'date' => $this->date,
                'time' => $this->time,
            ],
            [
                'name' => ['required', 'string', 'min:2'],
                'phone' => ['required', new PhilippinePhone],
                'email' => ['required', 'email'],
                'guests' => ['required', 'integer', 'min:1', 'max:' . $maxParty],
                'date' => ['required', 'date', 'after_or_equal:today'],
                'time' => ['required', 'string', Rule::in(self::slotTimeWhitelist())],
            ],
            [
                'name.min' => 'The name must be at least 2 characters.',
                'date.after_or_equal' => 'The date cannot be in the past.',
                'time.required' => 'Please select a time.',
                'time.in' => 'Please select a valid reservation time.',
                'guests.required' => 'Please enter the number of guests.',
                'guests.integer' => 'Party size must be a whole number.',
                'guests.min' => 'Party size must be at least :min.',
                'guests.max' => 'Party size cannot exceed :max (largest table capacity).',
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError($key, $message);
                }
            }

            return;
        }

        $this->subStep = 2;
    }

    public function goBackToGuestDetails(): void
    {
        $this->subStep = 1;
    }

    /**
     * Every 15-minute label from 10:00 through 22:45 (slot picker + validation whitelist).
     *
     * @return list<string>
     */
    private static function slotTimeWhitelist(): array
    {
        $out = [];
        $c = Carbon::parse('07:00');
        $end = Carbon::parse('23:00');
        while ($c <= $end) {
            $out[] = $c->format('H:i');
            $c->addMinutes(15);
        }

        return $out;
    }

    /**
     * Grouped slot grid for the time modal (Breakfast / Lunch / Tea Time / Dinner).
     *
     * @return array<string, array{inactive: bool, slots: list<array{time: string, available: bool}>}>
     */
    public function getSlotsByCategory(): array
    {
        return $this->slotsByCategory;
    }

    public function loadSlots(): void
    {
        $this->syncSlotsByCategory();

        if ($this->time !== '' && !$this->isTimeSlotAvailableInGrid($this->time)) {
            $this->time = '';
            $this->selectedSlot = '';
        }
    }

    /**
     * Builds $slotsByCategory, $availableSlots (flat compatibility), and $slotModalNoAvailabilityAll.
     */
    private function syncSlotsByCategory(): void
    {
        $this->slotsByCategory = [];
        $this->availableSlots = [
            'Breakfast' => [],
            'Lunch' => [],
            'Tea Time' => [],
            'Dinner' => [],
        ];
        $this->slotModalNoAvailabilityAll = false;

        if ($this->date === '' || $this->date === null) {
            foreach (['Breakfast', 'Lunch', 'Tea Time', 'Dinner'] as $k) {
                $this->slotsByCategory[$k] = ['inactive' => true, 'slots' => []];
            }
            $this->slotModalNoAvailabilityAll = true;

            return;
        }

        $date = Carbon::parse($this->date)->startOfDay();
        $party = max(1, (int) $this->guests);
        $capacity = $this->maxCoversPerSlot();

        [$openStr, $closeStr] = $this->operatingHoursForDate($date);

        $categoryRanges = [
            'Breakfast' => ['07:00', '10:45'],
            'Lunch' => ['11:00', '14:45'],
            'Tea Time' => ['15:00', '17:45'],
            'Dinner' => ['18:00', $closeStr],
        ];

        $anyBookable = false;

        foreach ($categoryRanges as $label => $range) {
            $slots = [];
            $segment = $this->intersectRangeWithOperatingHours($range[0], $range[1], $openStr, $closeStr);
            if ($segment === null) {
                $this->slotsByCategory[$label] = ['inactive' => true, 'slots' => []];
                $this->availableSlots[$label] = [];

                continue;
            }

            foreach ($this->rangeInclusive15($segment['start'], $segment['end']) as $t) {
                $used = $this->coversBookedForSlot($date, $t);
                $ok = ($used + $party) <= $capacity;
                if ($ok) {
                    $anyBookable = true;
                }
                $row = ['time' => $t, 'available' => $ok];
                $slots[] = $row;
                $this->availableSlots[$label][] = $row;
            }

            $this->slotsByCategory[$label] = [
                'inactive' => count($slots) === 0,
                'slots' => $slots,
            ];
        }

        $this->slotModalNoAvailabilityAll = !$anyBookable;
    }

    /**
     * Mon–Fri 9–10PM, Sat 9–11PM, Sun 7–10PM.
     *
     * @return array{0: string, 1: string} H:i open and close
     */
    private function operatingHoursForDate(Carbon $date): array
    {
        $dow = $date->dayOfWeek;

        if ($dow === Carbon::SATURDAY) {
            return ['09:00', '23:00'];
        }

        if ($dow === Carbon::SUNDAY) {
            return ['07:00', '22:00'];
        }

        return ['09:00', '22:00'];
    }

    /**
     * @return array{start: string, end: string}|null
     */
    private function intersectRangeWithOperatingHours(string $rangeStart, string $rangeEnd, string $open, string $close): ?array
    {
        $rs = Carbon::parse($rangeStart);
        $re = Carbon::parse($rangeEnd);
        $op = Carbon::parse($open);
        $cl = Carbon::parse($close);

        $start = $rs->max($op);
        $end = $re->min($cl);

        if ($start->gt($end)) {
            return null;
        }

        return ['start' => $start->format('H:i'), 'end' => $end->format('H:i')];
    }

    public function selectSlot(string $time): void
    {
        $this->loadSlots();
        if (!$this->isTimeSlotAvailableInGrid($time)) {
            return;
        }
        $this->selectedSlot = $time;
        $this->time = $time;
        $this->showSlotModal = false;
    }

    public function openSlotModal(): void
    {
        $this->loadSlots();
        $this->showSlotModal = true;
    }

    public function closeSlotModal(): void
    {
        $this->showSlotModal = false;
    }

    public function updatedDate($value): void
    {
        $this->loadSlots();
    }

    public function updatedGuests($value): void
    {
        $this->loadSlots();
    }

    public function updatedPartySize($value): void
    {
        $this->loadSlots();
    }

    public function updatedStep($value): void
    {
        if ((int) $value === 1) {
            $this->loadSlots();
        }
    }

    private function isTimeSlotAvailableInGrid(string $time): bool
    {
        foreach ($this->availableSlots as $slots) {
            foreach ($slots as $row) {
                if ($row['time'] === $time && $row['available']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, list<string>>
     */
    private function buildMealPeriodSlots(Carbon $date): array
    {
        $dow = $date->dayOfWeek;
        $isMonThu = $dow >= Carbon::MONDAY && $dow <= Carbon::THURSDAY;
        $lunchStart = $isMonThu ? '11:00' : '10:00';

        return [
            'Lunch' => $this->rangeInclusive15($lunchStart, '14:45'),
            'Tea' => $this->rangeInclusive15('15:00', '16:45'),
            'Dinner' => $this->rangeInclusive15('17:00', '22:00'),
        ];
    }

    /**
     * @return list<string>
     */
    private function rangeInclusive15(string $start, string $end): array
    {
        $out = [];
        $c = Carbon::parse($start);
        $endC = Carbon::parse($end);
        while ($c <= $endC) {
            $out[] = $c->format('H:i');
            $c->addMinutes(15);
        }

        return $out;
    }

    private function coversBookedForSlot(Carbon $date, string $time): int
    {
        return (int) Booking::query()
            ->whereDate('booked_at', $date->format('Y-m-d'))
            ->whereTime('booked_at', $time)
            ->whereIn('status', ['active', 'pending'])
            ->sum('party_size');
    }

    private function maxCoversPerSlot(): int
    {
        $sum = (int) Table::query()->sum('capacity');

        return $sum > 0 ? $sum : 40;
    }

    /**
     * Maximum party size for reservation validation: largest single table (cannot seat more at one table).
     * Falls back to config when no tables are defined yet.
     */
    private function maxPartySizeForReservation(): int
    {
        $max = (int) Table::query()->max('capacity');

        if ($max > 0) {
            return min($max, 99);
        }

        return max(1, (int) config('operations.reservation_max_party_fallback', 4));
    }

    public function getDepositPerGuestProperty(): int
    {
        return (int) Setting::get('deposit_per_guest', 500);
    }

    public function getTotalDepositProperty(): int
    {
        return $this->depositPerGuest * max(1, (int) $this->guests);
    }

    public function getPaymongoEnabledProperty(): bool
    {
        return app(PayMongoService::class)->isConfigured();
    }

    /**
     * Form submit action for step 1 (payment sub-step): PayMongo checkout vs manual reservation submit.
     */
    public function getFormSubmitHandlerProperty(): string
    {
        if (!$this->paymongoEnabled) {
            return 'submit';
        }

        return $this->reservationPaymentMode === 'manual' ? 'submit' : 'proceedToPayment';
    }

    private function checkHoneypot(): bool
    {
        return !empty($this->website);
    }

    /**
     * Bots filled the honeypot: do not record success, do not advance the flow.
     * Reset to the initial policy step with a cleared form (silent discard).
     */
    private function discardHoneypotSilently(): void
    {
        $this->success = false;
        $this->successPaymentMethod = null;
        $this->errorMessage = '';
        $this->bookingRef = '';
        $this->processing = false;
        $this->website = '';
        $this->step = 0;
        $this->subStep = 1;
        $this->name = '';
        $this->phone = '';
        $this->email = '';
        $this->guests = 2;
        $this->date = '';
        $this->time = '';
        $this->selectedSlot = '';
        $this->showSlotModal = false;
        $this->transactionNumber = '';
        $this->policyAcknowledged = false;
        $this->specialRequests = '';
        $this->availableSlots = [];
        $this->slotsByCategory = [];
        $this->slotModalNoAvailabilityAll = false;
        $this->reservationPaymentMode = 'online';
        $this->resetValidation();
    }

    private function checkRateLimit(): bool
    {
        if (app()->environment(['local', 'testing'])) {
            return true;
        }

        $key = 'reservation:' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->errorMessage = 'Too many reservation attempts. Please try again later.';
            return false;
        }
        RateLimiter::hit($key, 3600);
        return true;
    }

    private function rejectDuplicateReservation(): void
    {
        $this->addError(
            'phone',
            'You already have an active reservation. Check your SMS or contact us to manage it.'
        );
    }

    private function sanitize(): void
    {
        $this->name = strip_tags(trim($this->name));
        $this->phone = strip_tags(trim($this->phone));
        $this->email = strip_tags(trim($this->email));
        $this->date = strip_tags(trim($this->date));
        $this->time = strip_tags(trim($this->time));
        $this->transactionNumber = preg_replace('/\D/', '', strip_tags(trim((string) $this->transactionNumber)));
        $this->specialRequests = strip_tags(trim($this->specialRequests));
    }

    public function proceedToPayment(): void
    {
        if ($this->checkHoneypot()) {
            $this->discardHoneypotSilently();

            return;
        }
        $this->sanitize();
        $this->validate();
        $this->step = 2;
    }

    public function backToForm(): void
    {
        $this->step = 1;
        $this->subStep = 2;
        $this->errorMessage = '';
    }

    public function payDeposit(): void
    {
        if ($this->checkHoneypot()) {
            $this->discardHoneypotSilently();

            return;
        }
        if (!$this->checkRateLimit()) {
            return;
        }

        $this->sanitize();
        $this->validate();

        $this->processing = true;
        $this->errorMessage = '';

        $ref = self::generateSecureRef();
        $bookedAt = Carbon::parse("{$this->date} {$this->time}");

        // Typed reference is format-validated only; actual payment is confirmed via PayMongo checkout / webhook,
        // not by verifying these digits against GCash here. See docblock on submit() for the manual-QR edge case.

        $booking = $this->createGuardedBooking([
            'booking_ref' => $ref,
            'source' => DeviceContext::sourceForDb(request()),
            'device_type' => DeviceContext::deviceTypeForDb(request()),
            'table_id' => null,
            'customer_name' => $this->name,
            'customer_phone' => $this->phone,
            'customer_email' => $this->email,
            'party_size' => (int) $this->guests,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'paymongo',
            'deposit_amount' => $this->totalDeposit,
            'booked_at' => $bookedAt,
            'transaction_number' => $this->transactionNumber !== '' ? $this->transactionNumber : null,
            'account_number' => null,
            'policy_acknowledged' => $this->policyAcknowledged,
            'special_requests' => $this->specialRequests !== '' ? $this->specialRequests : null,
        ]);

        if (! $booking) {
            $this->processing = false;
            $this->rejectDuplicateReservation();

            return;
        }

        $service = app(PayMongoService::class);
        $amountCentavos = $this->totalDeposit * 100;
        $description = "Café Gervacios reservation deposit ({$this->guests} guests) — Ref: {$ref}";

        $result = $service->createPaymentLink(
            $amountCentavos,
            $description,
            $ref,
            url("/reservation/success?ref={$ref}"),
            url("/reservation/failed?ref={$ref}")
        );

        if (!$result) {
            $booking->update(['payment_status' => 'failed']);
            Log::warning('PayMongo checkout link creation failed for pending booking', [
                'booking_ref' => $booking->booking_ref,
            ]);
            $this->processing = false;
            $this->errorMessage = 'Unable to create payment link. Please try again or contact us directly.';
            return;
        }

        $booking->update([
            'paymongo_link_id' => $result['payment_link_id'],
        ]);

        $this->processing = false;
        $this->redirect($result['checkout_url']);
    }

    /**
     * Manual QR / bank transfer path (PayMongo disabled): we validate the reference only for
     * format (digits, minimum length) — we do not call PayMongo, GCash, or bank APIs to prove
     * a payment exists for this reference in real time. The booking is stored with
     * `payment_status` = pending_verification until staff confirm in admin (Bookings → Verify payment).
     *
     * Edge case: if a reference cannot be verified against PayMongo or GCash (no API match,
     * network failure, or manual QR only), it remains unverified on the booking until a staff
     * member manually confirms — that is intentional; we never block submission solely because
     * an external verifier could not validate the digits.
     */
    public function submit(): void
    {
        if ($this->checkHoneypot()) {
            $this->discardHoneypotSilently();

            return;
        }
        if (!$this->checkRateLimit()) {
            return;
        }

        $this->sanitize();
        $this->validate();

        $ref = self::generateSecureRef();
        $bookedAt = Carbon::parse("{$this->date} {$this->time}");

        $booking = $this->createGuardedBooking([
            'booking_ref' => $ref,
            'source' => DeviceContext::sourceForDb(request()),
            'device_type' => DeviceContext::deviceTypeForDb(request()),
            'table_id' => null,
            'customer_name' => $this->name,
            'customer_phone' => $this->phone,
            'customer_email' => $this->email,
            'party_size' => (int) $this->guests,
            'status' => 'pending',
            'payment_status' => 'pending_verification',
            'payment_method' => 'manual_qr',
            'deposit_amount' => $this->totalDeposit,
            'booked_at' => $bookedAt,
            'transaction_number' => $this->transactionNumber !== '' ? $this->transactionNumber : null,
            'account_number' => null,
            'policy_acknowledged' => $this->policyAcknowledged,
            'special_requests' => $this->specialRequests !== '' ? $this->specialRequests : null,
        ]);

        if (! $booking) {
            $this->rejectDuplicateReservation();

            return;
        }

        $this->bookingRef = $ref;
        $this->successPaymentMethod = 'manual_qr';
        $this->success = true;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createGuardedBooking(array $attributes): ?Booking
    {
        $lockKey = 'reservation:create:'.sha1($this->phone);

        try {
            return Cache::lock($lockKey, 10)->block(3, function () use ($attributes) {
                return DB::transaction(function () use ($attributes) {
                    if (app(BookingGuardService::class)->hasActivePendingReservation($this->phone, true)) {
                        return null;
                    }

                    return Booking::create($attributes);
                });
            });
        } catch (LockTimeoutException $e) {
            Log::warning('Reservation duplicate-prevention lock timed out', [
                'phone_hash' => sha1($this->phone),
            ]);

            return null;
        }
    }

    /**
     * Cryptographically secure booking reference: GRV-XXXXXXXX
     */
    public static function generateSecureRef(): string
    {
        do {
            $ref = 'GRV-' . strtoupper(bin2hex(random_bytes(4)));
        } while (Booking::where('booking_ref', $ref)->exists());

        return $ref;
    }

    public function render()
    {
        return view('livewire.reservation-form', [
            'reservationFee' => (int) Setting::get('reservation_fee', 150),
            'maxGuests' => $this->maxPartySizeForReservation(),
        ]);
    }
}
