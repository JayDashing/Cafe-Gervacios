<?php

namespace Tests\Unit;

use App\Services\PayMongoService;
use PHPUnit\Framework\TestCase;

class PayMongoServiceTest extends TestCase
{
    public function test_it_extracts_nested_payment_id_from_paymongo_link_payload(): void
    {
        $service = new PayMongoService();

        $paymentId = $service->extractPaymentId([
            [
                'data' => [
                    'id' => 'pay_test_123',
                    'type' => 'payment',
                ],
            ],
        ]);

        $this->assertSame('pay_test_123', $paymentId);
    }

    public function test_it_extracts_direct_payment_id_from_dashboard_shaped_payload(): void
    {
        $service = new PayMongoService();

        $paymentId = $service->extractPaymentId([
            [
                'id' => 'pay_direct_123',
                'type' => 'payment',
            ],
        ]);

        $this->assertSame('pay_direct_123', $paymentId);
    }
}
