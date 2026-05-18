<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\SmsLog;
use App\Services\NotificationService;
use App\Services\SmsService;
use App\Support\PhilippinePhoneNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PhilSmsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_philsms_send_uses_bearer_json_payload_and_normalizes_phone_number(): void
    {
        Setting::set('philsms_api_key', 'test-token');
        Setting::set('philsms_sender_id', 'CafeGV');

        Http::fake([
            'https://app.philsms.com/api/v3/sms/send' => Http::response([
                'status' => 'success',
                'data' => ['uid' => 'philsms-123'],
            ]),
        ]);

        $result = app(SmsService::class)->send('0917 123 4567', 'Hello from Cafe Gervacios');

        $this->assertSame('639171234567', $result['recipient']);
        $this->assertSame('queued', $result['status']);
        $this->assertSame('philsms-123', $result['provider_message_id']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://app.philsms.com/api/v3/sms/send'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request->hasHeader('Accept', 'application/json')
                && $request['recipient'] === '639171234567'
                && $request['sender_id'] === 'CafeGV'
                && $request['type'] === 'plain'
                && $request['message'] === 'Hello from Cafe Gervacios';
        });
    }

    public function test_notification_service_logs_successful_philsms_delivery(): void
    {
        Setting::set('philsms_api_key', 'test-token');
        Setting::set('philsms_sender_id', 'CafeGV');

        Http::fake([
            'https://app.philsms.com/api/v3/sms/send' => Http::response([
                'status' => 'success',
                'data' => ['uid' => 'philsms-log-1'],
            ]),
        ]);

        app(NotificationService::class)->sendSms('09171234567', 'admin_sms_test', [
            'venue' => 'Cafe Gervacios',
        ]);

        $log = SmsLog::query()->firstOrFail();

        $this->assertSame('639171234567', $log->phone);
        $this->assertSame('queued', $log->status);
        $this->assertSame('admin_sms_test', $log->template);
        $this->assertSame('philsms-log-1', $log->provider_message_id);
        $this->assertStringContainsString('PhilSMS is configured correctly', (string) $log->message);
    }

    public function test_notification_service_logs_philsms_failure_for_manual_feedback(): void
    {
        Setting::set('philsms_api_key', 'test-token');
        Setting::set('philsms_sender_id', 'CafeGV');

        Http::fake([
            'https://app.philsms.com/api/v3/sms/send' => Http::response([
                'status' => 'error',
                'message' => 'Invalid sender ID',
            ]),
        ]);

        try {
            app(NotificationService::class)->sendSms('09171234567', 'admin_sms_test', [
                'venue' => 'Cafe Gervacios',
            ]);
            $this->fail('Expected PhilSMS failure to be reported.');
        } catch (RuntimeException $e) {
            $this->assertSame('PhilSMS API error: Invalid sender ID', $e->getMessage());
        }

        $this->assertDatabaseHas('sms_logs', [
            'phone' => '639171234567',
            'template' => 'admin_sms_test',
            'status' => 'failed',
            'error_message' => 'PhilSMS API error: Invalid sender ID',
        ]);
    }

    public function test_philippine_phone_normalizer_accepts_local_and_international_mobile_formats(): void
    {
        $this->assertSame('639171234567', PhilippinePhoneNormalizer::normalize('09171234567'));
        $this->assertSame('639171234567', PhilippinePhoneNormalizer::normalize('+639171234567'));
        $this->assertSame('639171234567', PhilippinePhoneNormalizer::normalize('9171234567'));

        $this->expectException(\InvalidArgumentException::class);
        PhilippinePhoneNormalizer::normalize('021234567');
    }
}
