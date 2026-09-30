<?php

namespace App\Http\Controllers\Platform;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayFactory;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WebhookController extends Controller
{
    public function __construct(
        protected PaymentGatewayFactory $gateways,
        protected PaymentService $payments
    ) {}

    public function stripe(Request $request): JsonResponse
    {
        return $this->handle(PaymentMethod::STRIPE, $request);
    }

    public function paypal(Request $request): JsonResponse
    {
        return $this->handle(PaymentMethod::PAYPAL, $request);
    }

    /**
     * The webhook's own job is only to verify authenticity and identify
     * which payment changed. Rather than trusting the webhook body's status
     * field, it re-verifies the payment directly against the gateway (the
     * same PaymentService::verify() the polling endpoint uses), so a
     * forged-but-signed payload still can't mark a payment completed.
     */
    protected function handle(PaymentMethod $method, Request $request): JsonResponse
    {
        try {
            $reference = $this->gateways->make($method)->resolveWebhookReference($request);
        } catch (RuntimeException $e) {
            Log::warning('Rejected webhook with invalid signature.', [
                'gateway' => $method->value,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Invalid webhook signature.'], 400);
        }

        if ($reference === null) {
            return response()->json(['message' => 'Event type ignored.']);
        }

        $payment = Payment::where('payment_method', $method)
            ->where('gateway_reference', $reference)
            ->first();

        if (! $payment) {
            Log::warning('Webhook referenced an unknown payment.', [
                'gateway' => $method->value,
                'reference' => $reference,
            ]);

            return response()->json(['message' => 'Payment not found.']);
        }

        $this->payments->verify($payment);

        return response()->json(['message' => 'Webhook processed.']);
    }
}
