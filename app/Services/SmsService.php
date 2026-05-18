<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\PhilippinePhoneNormalizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SmsService
{
    private const API_BASE = 'https://dashboard.philsms.com/api/v3';
    private const SEND_ENDPOINT = self::API_BASE.'/sms/send';

    /**
     * Send one plain-text SMS through PhilSMS.
     *
     * @return array{recipient: string, status: string, provider_message_id: ?string, response: mixed}
     */
    public function send(string $phoneNumber, string $message): array
    {
        $recipient = PhilippinePhoneNormalizer::normalize($phoneNumber);
        $apiKey = $this->resolveApiKey();

        if ($apiKey === '') {
            throw new RuntimeException('PhilSMS API key not configured.');
        }

        $senderId = $this->resolveSenderId();

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withToken($apiKey)
                ->timeout(45)
                ->post($this->sendEndpoint(), [
                    'recipient' => $recipient,
                    'sender_id' => $senderId !== '' ? $senderId : 'PhilSMS',
                    'type' => 'plain',
                    'message' => $message,
                ]);
        } catch (\Throwable $e) {
            Log::warning('PhilSMS request failed', [
                'phone_prefix' => substr($recipient, 0, 5).'***',
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('PhilSMS request failed: '.$e->getMessage(), previous: $e);
        }

        $decoded = $response->json();
        if (! $response->successful()) {
            $error = is_array($decoded) ? json_encode($decoded) : $response->body();
            Log::warning('PhilSMS API returned an HTTP error', [
                'status' => $response->status(),
                'body' => $error,
            ]);

            throw new RuntimeException('PhilSMS API error: '.$error);
        }

        if (is_array($decoded) && strtolower((string) ($decoded['status'] ?? 'success')) === 'error') {
            $error = (string) ($decoded['message'] ?? json_encode($decoded));
            Log::warning('PhilSMS API returned an error response', [
                'message' => $error,
            ]);

            throw new RuntimeException('PhilSMS API error: '.$error);
        }

        return [
            'recipient' => $recipient,
            'status' => 'queued',
            'provider_message_id' => $this->extractProviderMessageId($decoded),
            'response' => $decoded,
        ];
    }

    public function isConfigured(): bool
    {
        return $this->resolveApiKey() !== '';
    }

    /**
     * Lightweight configured-token check using PhilSMS' documented list endpoint.
     *
     * @return array<string, mixed>|null
     */
    public function checkConnection(): ?array
    {
        $apiKey = $this->resolveApiKey();
        if ($apiKey === '') {
            return null;
        }

        try {
            $response = Http::acceptJson()
                ->withToken($apiKey)
                ->timeout(20)
                ->get(self::API_BASE.'/sms');

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            Log::warning('PhilSMS connection check failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function resolveApiKey(): string
    {
        return trim((string) Setting::get('philsms_api_key', config('services.philsms.api_key', '')));
    }

    public function resolveSenderId(): string
    {
        return trim((string) Setting::get('philsms_sender_id', config('services.philsms.sender_id', 'CafeGervacios')));
    }

    private function sendEndpoint(): string
    {
        return (string) config('services.philsms.endpoint', self::SEND_ENDPOINT);
    }

    private function extractProviderMessageId(mixed $decoded): ?string
    {
        if (! is_array($decoded)) {
            return null;
        }

        foreach ([
            'uid',
            'id',
            'message_id',
            'data.uid',
            'data.id',
            'data.message_id',
        ] as $key) {
            $value = data_get($decoded, $key);
            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }
}
