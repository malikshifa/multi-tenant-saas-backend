<?php

namespace App\Services\Payments\Gateways;

use App\Models\Payment;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayPalGateway implements PaymentGatewayInterface
{
    protected function baseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    protected function accessToken(): string
    {
        $response = Http::asForm()
            ->withBasicAuth(
                config('services.paypal.client_id'),
                config('services.paypal.client_secret')
            )
            ->post(
                $this->baseUrl().'/v1/oauth2/token',
                [
                    'grant_type' => 'client_credentials',
                ]
            );

        $response->throw();

        return $response->json('access_token');
    }

    public function createCheckout(Payment $payment): array
    {
        $token = $this->accessToken();

        $response = Http::withToken($token)
            ->acceptJson()
            ->post(
                $this->baseUrl().'/v2/checkout/orders',
                [
                    'intent' => 'CAPTURE',

                    'purchase_units' => [
                        [
                            'reference_id' => (string) $payment->id,

                            'description' => $payment
                                ->subscription
                                ->plan
                                ->name,

                            'amount' => [
                                'currency_code' => strtoupper(
                                    $payment->currency
                                ),

                                'value' => number_format(
                                    $payment->amount,
                                    2,
                                    '.',
                                    ''
                                ),
                            ],
                        ],
                    ],

                    'application_context' => [
                        'return_url' => config(
                            'services.payment.frontend_url'
                        )
                            .'/payment/paypal/success'
                            .'?payment_id='
                            .$payment->id,

                        'cancel_url' => config(
                            'services.payment.frontend_url'
                        )
                            .'/payment/paypal/cancel'
                            .'?payment_id='
                            .$payment->id,
                    ],
                ]
            );

        $response->throw();

        $data = $response->json();

        $approvalLink = collect(
            $data['links'] ?? []
        )->firstWhere('rel', 'approve');

        return [
            'gateway_reference' => $data['id'],
            'checkout_url' => $approvalLink['href'] ?? null,
        ];
    }

    public function verify(Payment $payment): array
    {
        $token = $this->accessToken();

        /*
         * Retrieve the order first.
         */
        $response = Http::withToken($token)
            ->acceptJson()
            ->get(
                $this->baseUrl()
                .'/v2/checkout/orders/'
                .$payment->gateway_reference
            );

        $response->throw();

        $order = $response->json();

        /*
         * PayPal order needs to be captured.
         */
        if ($order['status'] === 'APPROVED') {

            $captureResponse = Http::withToken($token)
                ->acceptJson()
                ->post(
                    $this->baseUrl()
                    .'/v2/checkout/orders/'
                    .$payment->gateway_reference
                    .'/capture'
                );

            $captureResponse->throw();

            $order = $captureResponse->json();
        }

        $capture = data_get(
            $order,
            'purchase_units.0.payments.captures.0'
        );

        return [
            'status' => $order['status'],

            'transaction_id' => $capture['id'] ?? null,

            'gateway_reference' => $order['id'],
        ];
    }

    public function refund(Payment $payment): array
    {
        $token = $this->accessToken();

        $response = Http::withToken($token)
            ->acceptJson()
            ->post(
                $this->baseUrl()
                .'/v2/payments/captures/'
                .$payment->transaction_id
                .'/refund',
                [
                    'amount' => [
                        'currency_code' => strtoupper(
                            $payment->currency
                        ),

                        'value' => number_format(
                            $payment->amount,
                            2,
                            '.',
                            ''
                        ),
                    ],
                ]
            );

        $response->throw();

        return [
            'refund_id' => $response->json('id'),

            'status' => $response->json('status'),
        ];
    }

    public function resolveWebhookReference(Request $request): ?string
    {
        $payload = $request->json()->all();

        $token = $this->accessToken();

        $response = Http::withToken($token)->acceptJson()->post(
            $this->baseUrl().'/v1/notifications/verify-webhook-signature',
            [
                'transmission_id' => $request->header('Paypal-Transmission-Id'),
                'transmission_time' => $request->header('Paypal-Transmission-Time'),
                'cert_url' => $request->header('Paypal-Cert-Url'),
                'auth_algo' => $request->header('Paypal-Auth-Algo'),
                'transmission_sig' => $request->header('Paypal-Transmission-Sig'),
                'webhook_id' => config('services.paypal.webhook_id'),
                'webhook_event' => $payload,
            ]
        );

        $response->throw();

        if ($response->json('verification_status') !== 'SUCCESS') {
            throw new RuntimeException('Invalid PayPal webhook signature.');
        }

        // Only these event types carry the order as their resource, matching
        // the gateway_reference we stored at checkout time.
        if (! in_array(data_get($payload, 'event_type'), ['CHECKOUT.ORDER.APPROVED', 'CHECKOUT.ORDER.COMPLETED'], true)) {
            return null;
        }

        return data_get($payload, 'resource.id');
    }
}
