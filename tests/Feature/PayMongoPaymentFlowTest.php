<?php

namespace Tests\Feature;

use App\Jobs\SendSmsJob;
use App\Livewire\ReservationSuccess;
use App\Models\Booking;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class PayMongoPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_mode_webhook_confirms_booking_once_and_rejects_duplicate_processing(): void
    {
        Queue::fake([SendSmsJob::class]);
        $this->configurePayMongoTestMode();

        $booking = $this->pendingPayMongoBooking();
        [$json, $signature] = $this->signedPayMongoPayload($this->paidPayload($booking), 'whsec_test_123');

        $this->postJson('/webhook/paymongo', json_decode($json, true), [
            'Paymongo-Signature' => $signature,
        ])->assertOk()->assertJson(['status' => 'success']);

        $this->postJson('/webhook/paymongo', json_decode($json, true), [
            'Paymongo-Signature' => $signature,
        ])->assertOk()->assertJson(['status' => 'already_processed']);

        $booking->refresh();
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame('active', $booking->status);
        $this->assertSame('pay_test_123', $booking->paymongo_payment_id);
        $this->assertNotNull($booking->paid_at);

        Queue::assertPushed(SendSmsJob::class, 1);
    }

    public function test_test_mode_webhook_rejects_live_signature_header(): void
    {
        $this->configurePayMongoTestMode();
        $booking = $this->pendingPayMongoBooking();
        [$json, $signature] = $this->signedPayMongoPayload($this->paidPayload($booking), 'whsec_test_123', 'li');

        $this->postJson('/webhook/paymongo', json_decode($json, true), [
            'Paymongo-Signature' => $signature,
        ])->assertStatus(403);

        $this->assertSame('pending', $booking->fresh()->payment_status);
    }

    public function test_success_page_polling_confirms_booking_when_webhook_is_delayed(): void
    {
        Queue::fake([SendSmsJob::class]);
        $this->configurePayMongoTestMode();
        $booking = $this->pendingPayMongoBooking();

        Http::fake([
            'https://api.paymongo.com/v1/links/link_test_123' => Http::response([
                'data' => [
                    'id' => 'link_test_123',
                    'attributes' => [
                        'status' => 'paid',
                        'payments' => [
                            [
                                'data' => [
                                    'id' => 'pay_test_456',
                                    'type' => 'payment',
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        Livewire::test(ReservationSuccess::class, ['bookingRef' => $booking->booking_ref])
            ->assertSet('bookingRef', $booking->booking_ref);

        $booking->refresh();
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame('active', $booking->status);
        $this->assertSame('pay_test_456', $booking->paymongo_payment_id);

        Queue::assertPushed(SendSmsJob::class, 1);
    }

    public function test_success_page_polling_confirms_checkout_session_when_webhook_is_delayed(): void
    {
        Queue::fake([SendSmsJob::class]);
        $this->configurePayMongoTestMode();
        $booking = $this->pendingPayMongoBooking('cs_test_123');

        Http::fake([
            'https://api.paymongo.com/v1/checkout_sessions/cs_test_123' => Http::response([
                'data' => [
                    'id' => 'cs_test_123',
                    'attributes' => [
                        'status' => 'active',
                        'payment_intent' => [
                            'attributes' => [
                                'status' => 'succeeded',
                                'payments' => [
                                    [
                                        'data' => [
                                            'id' => 'pay_checkout_789',
                                            'type' => 'payment',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        Livewire::test(ReservationSuccess::class, ['bookingRef' => $booking->booking_ref])
            ->assertSet('bookingRef', $booking->booking_ref);

        $booking->refresh();
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame('active', $booking->status);
        $this->assertSame('pay_checkout_789', $booking->paymongo_payment_id);

        Queue::assertPushed(SendSmsJob::class, 1);
    }

    public function test_checkout_session_webhook_confirms_pending_booking(): void
    {
        Queue::fake([SendSmsJob::class]);
        $this->configurePayMongoTestMode();

        $booking = $this->pendingPayMongoBooking('cs_test_123');
        $payload = $this->checkoutSessionPaidPayload($booking);

        [$json, $signature] = $this->signedPayMongoPayload($payload, 'whsec_test_123');

        $this->postJson('/webhook/paymongo', json_decode($json, true), [
            'Paymongo-Signature' => $signature,
        ])->assertOk()->assertJson(['status' => 'success']);

        $booking->refresh();
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame('active', $booking->status);
        $this->assertSame('pay_checkout_123', $booking->paymongo_payment_id);

        Queue::assertPushed(SendSmsJob::class, 1);
    }

    public function test_failed_payment_webhook_marks_pending_booking_failed(): void
    {
        Queue::fake([SendSmsJob::class]);
        $this->configurePayMongoTestMode();

        $booking = $this->pendingPayMongoBooking();
        $payload = $this->paidPayload($booking);
        $payload['data']['attributes']['type'] = 'link.payment.failed';

        [$json, $signature] = $this->signedPayMongoPayload($payload, 'whsec_test_123');

        $this->postJson('/webhook/paymongo', json_decode($json, true), [
            'Paymongo-Signature' => $signature,
        ])->assertOk()->assertJson(['status' => 'payment_failed_recorded']);

        $booking->refresh();
        $this->assertSame('failed', $booking->payment_status);
        $this->assertSame('cancelled', $booking->status);

        Queue::assertPushed(SendSmsJob::class, 1);
    }

    private function configurePayMongoTestMode(): void
    {
        Config::set('services.paymongo.mode', 'test');
        Config::set('services.paymongo.allow_live', false);
        Config::set('services.paymongo.webhook_tolerance_seconds', 300);

        Setting::set('paymongo_secret_key', 'sk_test_123');
        Setting::set('paymongo_webhook_secret', 'whsec_test_123');
    }

    private function pendingPayMongoBooking(string $paymongoLinkId = 'link_test_123'): Booking
    {
        return Booking::create([
            'booking_ref' => 'CG-TEST-123',
            'source' => 'website',
            'device_type' => 'desktop',
            'customer_name' => 'Sandbox Guest',
            'customer_phone' => '09171234567',
            'customer_email' => 'guest@example.com',
            'party_size' => 2,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'paymongo',
            'paymongo_link_id' => $paymongoLinkId,
            'deposit_amount' => 1000,
            'booked_at' => now()->addDay(),
            'policy_acknowledged' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutSessionPaidPayload(Booking $booking): array
    {
        return [
            'data' => [
                'id' => 'evt_checkout_123',
                'type' => 'event',
                'attributes' => [
                    'type' => 'checkout_session.payment.paid',
                    'data' => [
                        'id' => 'cs_test_123',
                        'type' => 'checkout_session',
                        'attributes' => [
                            'reference_number' => $booking->booking_ref,
                            'metadata' => [
                                'booking_ref' => $booking->booking_ref,
                            ],
                            'payment_intent' => [
                                'attributes' => [
                                    'payments' => [
                                        [
                                            'data' => [
                                                'id' => 'pay_checkout_123',
                                                'type' => 'payment',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paidPayload(Booking $booking): array
    {
        return [
            'data' => [
                'id' => 'evt_test_123',
                'type' => 'event',
                'attributes' => [
                    'type' => 'link.payment.paid',
                    'data' => [
                        'id' => 'link_test_123',
                        'type' => 'link',
                        'attributes' => [
                            'remarks' => $booking->booking_ref,
                            'payments' => [
                                [
                                    'data' => [
                                        'id' => 'pay_test_123',
                                        'type' => 'payment',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function signedPayMongoPayload(array $payload, string $secret, string $signatureKey = 'te'): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$json, $secret);

        return [$json, "t={$timestamp},{$signatureKey}={$signature}"];
    }
}
