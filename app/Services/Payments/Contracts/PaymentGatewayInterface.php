<?php

namespace App\Services\Payments\Contracts;

use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /**
     * Create hosted checkout.
     */
    public function createCheckout(Payment $payment): array;

    /**
     * Verify/capture the payment after checkout.
     */
    public function verify(Payment $payment): array;

    /**
     * Refund a completed payment.
     */
    public function refund(Payment $payment): array;

    /**
     * Verify an incoming webhook request's authenticity and, for an event
     * type worth acting on, return the gateway_reference (checkout
     * session / order id) it concerns. Returns null for event types this
     * app doesn't need to react to (the caller should still acknowledge
     * the webhook with a 2xx response).
     *
     * @throws \RuntimeException when the signature is missing or invalid.
     */
    public function resolveWebhookReference(Request $request): ?string;
}
