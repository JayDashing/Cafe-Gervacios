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
            'booking_confirmed' => 'Hi :name, your table at :venue is confirmed. Ref: :booking_ref. Show this SMS when you arrive.',
            'queue_joined' => 'Hi :name, you are #:position in line (#:queue_no) at :venue. Est. wait: :wait mins. We will SMS when your table is ready.',
            'table_ready' => ':name, your table at :venue is ready! Please come to the host desk within :minutes minutes. Your confirmation code: :code',
            'queue_skipped' => ':name, we could not seat you in time at :venue. Please rejoin the queue if you are still here.',
            'wait_extended' => ':name, wait time at :venue is now about :wait minutes. Thanks for your patience.',
            'reminder_24h' => 'Reminder: :name, reservation at :venue tomorrow/ref :ref at :time.',
            'reminder_2h' => 'Reminder: :name, reservation at :venue in ~2h. Ref: :ref. Time: :time.',
            'late_checkin' => ':name, we have not checked you in for :ref at :venue. Please see the host desk.',
            'no_show' => ':name, your reservation :ref at :venue was marked no-show. Contact us if this is a mistake.',
            'automation_error' => 'Automation error [:task] at :venue: :message. Check /admin/logs.',
            'admin_sms_test' => 'Cafe Gervacios: admin panel SMS test. If you received this, PhilSMS is configured correctly.',
            'payment_verification_rejected' => 'Your reservation payment could not be verified. Please contact us or rebook at :site_url.',
            'payment_rejected' => 'Your reservation payment could not be verified. Please contact us or rebook at :site_url.',
        ];

        $message = $messages[$template] ?? 'Notification from :venue';

        foreach ($vars as $key => $value) {
            $message = str_replace(":{$key}", (string) $value, $message);
        }

        return $message;
    }
}
