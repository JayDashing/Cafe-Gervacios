<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PayMongoService
{
    private function getSecretKey(): string
    {
        return $this->settingOrConfig('paymongo_secret_key', 'services.paymongo.secret_key');
    }

    private function getWebhookSecret(): string
    {
        return $this->settingOrConfig('paymongo_webhook_secret', 'services.paymongo.webhook_secret');
    }

    private function settingOrConfig(string $settingKey, string $configKey): string
    {
        try {
            $value = Setting::query()->where('key', $settingKey)->value('value');

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        } catch (\Throwable) {
            //
        }

        return trim((string) config($configKey, ''));
    }

    public function isConfigured(): bool
    {
        return $this->usableSecretKey() !== null;
    }

    public function isTestMode(): bool
    {
        return strtolower((string) config('services.paymongo.mode', 'test')) === 'test';
    }

    public function liveKeysAllowed(): bool
    {
        return (bool) config('services.paymongo.allow_live', false);
    }

    public function isLiveSecretKey(string $key): bool
    {
        return Str::startsWith(strtolower(trim($key)), 'sk_live');
    }

    public function isTestSecretKey(string $key): bool
    {
        return Str::startsWith(strtolower(trim($key)), 'sk_test');
    }

    private function usableSecretKey(): ?string
    {
        $secretKey = $this->getSecretKey();

        if ($secretKey === '') {
            Log::warning('PayMongo secret key is not configured.');

            return null;
        }

        if ($this->isLiveSecretKey($secretKey) && ! $this->liveKeysAllowed()) {
            Log::critical('Blocked PayMongo live secret key in non-live configuration.');

            return null;
        }

        if ($this->isTestMode() && ! $this->isTestSecretKey($secretKey)) {
            Log::warning('PayMongo is in test mode but the configured secret key is not a test key.');

            return null;
        }

        return $secretKey;
    }

    /**
     * Create a PayMongo checkout session.
     *
     * @return array{checkout_url: string, payment_link_id: string}|null
     */
    public function createPaymentLink(int $amountCentavos, string $description, string $bookingRef, string $successUrl, string $failedUrl): ?array
    {
        $secretKey = $this->usableSecretKey();
        if ($secretKey === null) {
            return null;
        }

        try {
            $response = Http::withBasicAuth($secretKey, '')
                ->post('https://api.paymongo.com/v1/checkout_sessions', [
                    'data' => [
                        'attributes' => [
                            'send_email_receipt' => false,
                            'show_description'   => true,
                            'show_line_items'    => true,
                            'description'        => $description,
                            'reference_number'   => $bookingRef,
                            'payment_method_types' => ['qrph', 'gcash', 'paymaya', 'card'],
                            'success_url'        => $successUrl,
                            'cancel_url'         => $failedUrl,
                            'metadata'           => [
                                'booking_ref' => $bookingRef,
                            ],
                            'line_items'         => [
                                [
                                    'currency'    => 'PHP',
                                    'amount'      => $amountCentavos,
                                    'description' => $description,
                                    'name'        => 'Reservation deposit',
                                    'quantity'    => 1,
                                ],
                            ],
                        ],
                    ],
                ]);

            if (!$response->successful()) {
                Log::error('PayMongo createPaymentLink failed', [
                    'status' => $response->status(),
                    'body'   => $this->safeResponseBody($response->body()),
                    'booking_ref' => $bookingRef,
                ]);
                return null;
            }

            $data = $response->json('data');

            return [
                'checkout_url'    => $data['attributes']['checkout_url'],
                'payment_link_id' => $data['id'],
            ];
        } catch (\Exception $e) {
            Log::error('PayMongo createPaymentLink exception', [
                'booking_ref' => $bookingRef,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get payment link / checkout session status from PayMongo.
     *
     * @return array{status: string, payments: array}|null
     */
    public function getPaymentLink(string $linkId): ?array
    {
        $secretKey = $this->usableSecretKey();
        if ($secretKey === null) {
            return null;
        }

        try {
            if (Str::startsWith($linkId, 'cs_')) {
                return $this->getCheckoutSession($secretKey, $linkId);
            }

            $response = Http::withBasicAuth($secretKey, '')
                ->get("https://api.paymongo.com/v1/links/{$linkId}");

            if (!$response->successful()) {
                Log::warning('PayMongo getPaymentLink failed', [
                    'status' => $response->status(),
                    'link_id' => $linkId,
                ]);

                return null;
            }

            $data = $response->json('data');

            $payments = $data['attributes']['payments'] ?? [];
            if (! is_array($payments)) {
                $payments = [];
            }

            return [
                'status'     => $data['attributes']['status'],
                'payments'   => $payments,
                'payment_id' => $this->extractPaymentId($payments),
            ];
        } catch (\Exception $e) {
            Log::error('PayMongo getPaymentLink exception', [
                'link_id' => $linkId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * @return array{status: string, payments: array, payment_id: string|null}|null
     */
    private function getCheckoutSession(string $secretKey, string $checkoutSessionId): ?array
    {
        $response = Http::withBasicAuth($secretKey, '')
            ->get("https://api.paymongo.com/v1/checkout_sessions/{$checkoutSessionId}");

        if (! $response->successful()) {
            Log::warning('PayMongo getCheckoutSession failed', [
                'status' => $response->status(),
                'checkout_session_id' => $checkoutSessionId,
            ]);

            return null;
        }

        $data = $response->json('data');
        $payments = data_get($data, 'attributes.payments', []);
        if (! is_array($payments) || $payments === []) {
            $payments = data_get($data, 'attributes.payment_intent.attributes.payments', []);
        }
        if (! is_array($payments)) {
            $payments = [];
        }

        $status = (string) data_get($data, 'attributes.payment_intent.attributes.status', data_get($data, 'attributes.status', ''));
        if (in_array($status, ['succeeded', 'paid'], true) || $payments !== []) {
            $status = 'paid';
        }

        return [
            'status' => $status,
            'payments' => $payments,
            'payment_id' => $this->extractPaymentId($payments),
        ];
    }

    /**
     * PayMongo link payloads nest payments as payments[0].data.id, but older
     * or dashboard-shaped payloads can expose an id directly.
     */
    public function extractPaymentId(array $payments): ?string
    {
        foreach ($payments as $payment) {
            if (! is_array($payment)) {
                continue;
            }

            $id = data_get($payment, 'data.id') ?? data_get($payment, 'id');
            if (is_string($id) && $id !== '') {
                return $id;
            }
        }

        return null;
    }

    /**
     * Verify PayMongo webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signatureHeader): bool
    {
        $webhookSecret = $this->getWebhookSecret();
        if ($webhookSecret === '') {
            Log::warning('PayMongo webhook secret is not configured.');

            return false;
        }

        $parts = $this->parseSignatureHeader($signatureHeader);

        $timestamp = $parts['t'] ?? '';
        $testSig   = $parts['te'] ?? '';
        $liveSig   = $parts['li'] ?? '';

        if (! ctype_digit((string) $timestamp)) {
            return false;
        }

        $tolerance = max(1, (int) config('services.paymongo.webhook_tolerance_seconds', 300));
        if (abs(now()->timestamp - (int) $timestamp) > $tolerance) {
            Log::warning('PayMongo webhook signature timestamp outside tolerance.');

            return false;
        }

        $expectedPayload = "{$timestamp}.{$payload}";
        $computedSig = hash_hmac('sha256', $expectedPayload, $webhookSecret);

        $signatureToCheck = $this->isTestMode() ? $testSig : $liveSig;

        if ($signatureToCheck === '') {
            return false;
        }

        return hash_equals($computedSig, $signatureToCheck);
    }

    /**
     * @return array<string, string>
     */
    private function parseSignatureHeader(string $signatureHeader): array
    {
        $parts = [];

        foreach (explode(',', $signatureHeader) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) === 2) {
                $parts[trim($kv[0])] = trim($kv[1]);
            }
        }

        return $parts;
    }

    private function safeResponseBody(string $body): string
    {
        $masked = preg_replace('/(sk|pk|whsec)_(live|test)_[A-Za-z0-9_\-]+/', '$1_$2_***', $body) ?? '';

        return Str::limit($masked, 1000);
    }
}
