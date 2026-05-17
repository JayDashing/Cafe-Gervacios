<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\BookingConfirmationService;
use App\Services\PayMongoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayMongoWebhookController extends Controller
{
    public function handle(Request $request, PayMongoService $payMongoService): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Paymongo-Signature', '');

        if (! $payMongoService->verifyWebhookSignature($payload, $signature)) {
            Log::warning('PayMongo webhook signature verification failed', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $data = $request->input('data', []);
        $eventType = data_get($data, 'attributes.type', '');
        $resourceData = data_get($data, 'attributes.data', []);

        if (! is_array($resourceData)) {
            Log::warning('PayMongo webhook payload missing resource data', [
                'type' => $eventType,
            ]);

            return response()->json(['error' => 'Invalid payload'], 400);
        }

        Log::info('PayMongo webhook received', ['type' => $eventType]);

        if (in_array($eventType, ['link.payment.paid', 'checkout_session.payment.paid'], true)) {
            return $this->handlePaymentPaid($resourceData, $payMongoService);
        }

        if (in_array($eventType, ['link.payment.failed', 'payment.failed', 'link.payment.cancelled', 'checkout_session.payment.failed'], true)) {
            return $this->handlePaymentFailed($resourceData, $payMongoService);
        }

        return response()->json(['status' => 'ignored']);
    }

    private function handlePaymentPaid(array $resourceData, PayMongoService $payMongoService): JsonResponse
    {
        $paymentId = $payMongoService->extractPaymentId($this->paymentsFromResource($resourceData));
        $booking = $this->findAndLockBookingFromResource($resourceData, $paymentId);

        if (! $booking) {
            $this->logBookingNotFound($resourceData);

            return response()->json(['status' => 'booking_not_found'], 200);
        }

        $alreadyPaid = $booking->payment_status === 'paid';

        if ($paymentId !== null && $booking->paymongo_payment_id !== $paymentId) {
            $booking->update(['paymongo_payment_id' => $paymentId]);
            $booking->refresh();
        }

        $table = app(BookingConfirmationService::class)->confirm($booking);

        if (! $table) {
            Log::warning('PayMongo webhook: no table available for auto-assignment', [
                'booking_ref' => $booking->booking_ref,
            ]);
        }

        Log::info('PayMongo payment confirmed for booking', [
            'booking_ref' => $booking->booking_ref,
            'already_paid' => $alreadyPaid,
        ]);

        return response()->json(['status' => $alreadyPaid ? 'already_processed' : 'success']);
    }

    private function handlePaymentFailed(array $resourceData, PayMongoService $payMongoService): JsonResponse
    {
        $paymentId = $payMongoService->extractPaymentId($this->paymentsFromResource($resourceData));
        $booking = $this->findAndLockBookingFromResource($resourceData, $paymentId);

        if (! $booking) {
            $this->logBookingNotFound($resourceData);

            return response()->json(['status' => 'booking_not_found'], 200);
        }

        if ($booking->payment_status === 'paid') {
            Log::warning('PayMongo failed event ignored because booking is already paid', [
                'booking_ref' => $booking->booking_ref,
            ]);

            return response()->json(['status' => 'paid_booking_ignored'], 200);
        }

        if ($paymentId !== null && $booking->paymongo_payment_id !== $paymentId) {
            $booking->update(['paymongo_payment_id' => $paymentId]);
            $booking->refresh();
        }

        app(BookingConfirmationService::class)->reject(
            $booking,
            'PayMongo reported that the payment did not complete.'
        );

        Log::info('PayMongo payment failure recorded for booking', [
            'booking_ref' => $booking->booking_ref,
        ]);

        return response()->json(['status' => 'payment_failed_recorded']);
    }

    /**
     * @return array<int, mixed>
     */
    private function paymentsFromResource(array $resourceData): array
    {
        $payments = data_get($resourceData, 'attributes.payments', []);
        if (! is_array($payments) || $payments === []) {
            $payments = data_get($resourceData, 'attributes.payment_intent.attributes.payments', []);
        }

        return is_array($payments) ? $payments : [];
    }

    private function findAndLockBookingFromResource(array $resourceData, ?string $paymentId = null): ?Booking
    {
        $linkId = data_get($resourceData, 'id');
        $remarks = data_get($resourceData, 'attributes.remarks')
            ?? data_get($resourceData, 'attributes.reference_number')
            ?? data_get($resourceData, 'attributes.metadata.booking_ref');

        return DB::transaction(function () use ($remarks, $linkId, $paymentId) {
            $booking = null;

            if (is_string($remarks) && $remarks !== '') {
                $booking = Booking::query()
                    ->where('booking_ref', $remarks)
                    ->lockForUpdate()
                    ->first();
            }

            if (! $booking && is_string($linkId) && $linkId !== '') {
                $booking = Booking::query()
                    ->where('paymongo_link_id', $linkId)
                    ->lockForUpdate()
                    ->first();
            }

            if (! $booking && is_string($paymentId) && $paymentId !== '') {
                $booking = Booking::query()
                    ->where('paymongo_payment_id', $paymentId)
                    ->lockForUpdate()
                    ->first();
            }

            return $booking?->fresh();
        });
    }

    private function logBookingNotFound(array $resourceData): void
    {
        Log::warning('PayMongo webhook: booking not found', [
            'link_id' => data_get($resourceData, 'id'),
            'remarks' => data_get($resourceData, 'attributes.remarks'),
        ]);
    }
}
