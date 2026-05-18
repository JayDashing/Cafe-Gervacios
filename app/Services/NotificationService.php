<?php

namespace App\Services;

use App\Mail\BookingConfirmedMail;
use App\Models\Setting;
use App\Models\SmsLog;
use App\Support\PhilippinePhoneNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * SMS delivery through PhilSMS.
 *
 * Never call from an HTTP request cycle for user-facing flows - use SendSmsJob.
 */
class NotificationService
{
    public function __construct(private ?SmsService $smsService = null)
    {
        $this->smsService ??= app(SmsService::class);
    }

    public function sendSms(string $phone, string $template, array $vars): void
    {
        $messageBody = $this->buildMessage($template, $vars);

        try {
            $normalized = PhilippinePhoneNormalizer::normalize($phone);
        } catch (\InvalidArgumentException $e) {
            $this->persistSmsLogFailedFormat($phone, $template, $vars, $messageBody, $e->getMessage());

            throw $e;
        }

        if (Setting::get('sms_enabled', '1') !== '1') {
            Log::info('SMS not sent (sms_enabled is off)', [
                'template' => $template,
                'message' => $messageBody,
                'phone_prefix' => strlen($normalized) >= 5 ? substr($normalized, 0, 5).'***' : $normalized,
            ]);

            $this->persistSmsLog(
                $normalized,
                $template,
                $vars,
                $messageBody,
                'skipped',
                null,
                'SMS sending disabled (sms_enabled).'
            );

            return;
        }

        if (! $this->smsService->isConfigured()) {
            Log::warning('SMS skipped: PhilSMS API key not configured.', [
                'template' => $template,
                'phone_prefix' => strlen($normalized) >= 5 ? substr($normalized, 0, 5).'***' : $normalized,
            ]);

            $this->persistSmsLog(
                $normalized,
                $template,
                $vars,
                $messageBody,
                'skipped',
                null,
                'PhilSMS API key not configured.'
            );

            $this->maybeSendBookingConfirmedEmailFallback($template, $vars);

            return;
        }

        try {
            $result = $this->smsService->send($normalized, $messageBody);

            $this->persistSmsLog(
                $normalized,
                $template,
                $vars,
                $messageBody,
                (string) ($result['status'] ?? 'queued'),
                $result['provider_message_id'] ?? null,
                null
            );
        } catch (\Throwable $e) {
            $this->persistSmsLog($normalized, $template, $vars, $messageBody, 'failed', null, $e->getMessage());

            throw $e;
        }
    }

    /**
     * PhilSMS does not expose account credits in the public SMS docs; this checks API connectivity.
     *
     * @return array<string, mixed>|null
     */
    public function checkSmsProviderConnection(): ?array
    {
        return $this->smsService->checkConnection();
    }

    public function fetchAccountCredits(): ?array
    {
        return $this->checkSmsProviderConnection();
    }

    public function isConfigured(): bool
    {
        return $this->smsService->isConfigured();
    }

    public function resolveApiKey(): string
    {
        return $this->smsService->resolveApiKey();
    }

    public function resolveSenderName(): string
    {
        return $this->smsService->resolveSenderId();
    }

    private function persistSmsLogFailedFormat(string $rawPhone, string $template, array $vars, string $messageBody, string $errorMessage): void
    {
        try {
            SmsLog::create([
                'phone_hash' => hash('sha256', (string) config('app.key').$rawPhone),
                'phone' => null,
                'message' => $messageBody,
                'status' => 'failed',
                'provider_message_id' => null,
                'error_message' => $errorMessage,
                'template' => $template,
                'context' => $vars,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('SMS log write failed', ['error' => $e->getMessage()]);
        }
    }

    private function persistSmsLog(
        ?string $normalizedPhone,
        string $template,
        array $vars,
        string $messageBody,
        string $status,
        ?string $providerMessageId,
        ?string $errorMessage
    ): void {
        try {
            SmsLog::create([
                'phone_hash' => $normalizedPhone
                    ? hash('sha256', (string) config('app.key').$normalizedPhone)
                    : hash('sha256', (string) config('app.key').'unknown'),
                'phone' => $normalizedPhone,
                'message' => $messageBody,
                'status' => $status,
                'provider_message_id' => $providerMessageId,
                'error_message' => $errorMessage,
                'template' => $template,
                'context' => $vars,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('SMS log write failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * When SMS cannot be sent (no PhilSMS key), optionally email booking confirmation.
     */
    private function maybeSendBookingConfirmedEmailFallback(string $template, array $vars): void
    {
        if ($template !== 'booking_confirmed') {
            return;
        }

        $email = trim((string) ($vars['customer_email'] ?? ''));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        $bookingRef = trim((string) ($vars['booking_ref'] ?? ''));
        if ($bookingRef === '') {
            return;
        }

        $customerName = (string) ($vars['name'] ?? $vars['customer_name'] ?? 'Guest');
        $partySize = max(1, (int) ($vars['party_size'] ?? 1));
        $bookedAt = isset($vars['booked_at'])
            ? Carbon::parse($vars['booked_at'])
            : now();
        $venueName = (string) config('app.name', 'Cafe Gervacios');

        try {
            Mail::to($email)->send(new BookingConfirmedMail(
                customerName: $customerName,
                bookingRef: $bookingRef,
                bookedAt: $bookedAt,
                partySize: $partySize,
                venueName: $venueName,
            ));
        } catch (\Throwable $e) {
            Log::warning('Booking confirmation email fallback failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildMessage(string $template, array $vars): string
    {
        $messages = [
            'booking_confirmed' => 'Cafe Gervacios: Booking confirmed. Ref :booking_ref. Show this SMS on arrival.',
            'queue_joined' => 'Cafe Gervacios: Queue #:position, No. :queue_no. Est wait :wait min. We will text when ready.',
            'table_ready' => 'Cafe Gervacios: Your table is ready. Go to the host desk in :minutes min. Code: :code',
            'queue_skipped' => 'Cafe Gervacios: We could not seat you in time. Please rejoin the queue if still waiting.',
            'wait_extended' => 'Cafe Gervacios: Updated wait time is about :wait min. Thank you.',
            'reminder_24h' => 'Cafe Gervacios: Reminder for ref :ref tomorrow at :time.',
            'reminder_2h' => 'Cafe Gervacios: Reminder for ref :ref today at :time.',
            'late_checkin' => 'Cafe Gervacios: Please check in for ref :ref at the host desk.',
            'no_show' => 'Cafe Gervacios: Ref :ref was marked no-show. Contact staff if this is wrong.',
            'automation_error' => 'Cafe Gervacios: Automation error [:task]. Check admin logs.',
            'admin_sms_test' => 'Cafe Gervacios SMS test. PhilSMS works.',
            'payment_verification_rejected' => 'Cafe Gervacios: Payment not verified. Please contact staff or rebook.',
            'payment_rejected' => 'Cafe Gervacios: Payment not verified. Please contact staff or rebook.',
        ];

        $message = $messages[$template] ?? 'Notification from :venue';

        foreach ($vars as $key => $value) {
            $message = str_replace(":{$key}", (string) $value, $message);
        }

        return $message;
    }
}
