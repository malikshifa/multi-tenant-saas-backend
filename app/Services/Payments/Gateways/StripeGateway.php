<?php

namespace App\Services\Payments\Gateways;

use App\Models\Payment;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Request;
use RuntimeException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeGateway implements PaymentGatewayInterface
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(
            config('services.stripe.secret')
        );
    }

    public function createCheckout(Payment $payment): array
    {
        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'payment',

            'line_items' => [
                [
                    'price_data' => [
                        'currency' => strtolower(
                            $payment->currency
                        ),

                        'product_data' => [
                            'name' => $payment
                                ->subscription
                                ->plan
                                ->name,
                        ],

                        'unit_amount' => (int) round(
                            $payment->amount * 100
                        ),
                    ],

                    'quantity' => 1,
                ],
            ],

            'success_url' => config('services.payment.frontend_url')
                .'/payment/success'
                .'?payment_id='.$payment->id
                .'&session_id={CHECKOUT_SESSION_ID}',

            'cancel_url' => config('services.payment.frontend_url')
                .'/payment/cancel'
                .'?payment_id='.$payment->id,

            'metadata' => [
                'payment_id' => (string) $payment->id,
                'tenant_id' => (string) $payment->tenant_id,
                'subscription_id' => (string) $payment->subscription_id,
            ],
        ]);

        return [
            'gateway_reference' => $session->id,
            'checkout_url' => $session->url,
        ];
    }

    public function verify(Payment $payment): array
    {
        $session = $this->stripe->checkout->sessions->retrieve(
            $payment->gateway_reference
        );

        return [
            'status' => $session->payment_status,
            'transaction_id' => $session->payment_intent,
            'gateway_reference' => $session->id,
        ];
    }

    public function refund(Payment $payment): array
    {
        $refund = $this->stripe->refunds->create([
            'payment_intent' => $payment->transaction_id,
        ]);

        return [
            'refund_id' => $refund->id,
            'status' => $refund->status,
        ];
    }

    public function resolveWebhookReference(Request $request): ?string
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                (string) config('services.stripe.webhook_secret')
            );
        } catch (SignatureVerificationException|\UnexpectedValueException $e) {
            throw new RuntimeException('Invalid Stripe webhook signature.', previous: $e);
        }

        // Only these event types carry a checkout session as their object,
        // matching the gateway_reference we stored at checkout time.
        if (! in_array($event->type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.async_payment_failed'], true)) {
            return null;
        }

        return $event->data->object->id;
    }
}
