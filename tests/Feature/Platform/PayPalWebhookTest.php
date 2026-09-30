<?php

namespace Tests\Feature\Platform;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Payments\Gateways\PayPalGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayPalWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function fakePayPalHttp(string $verificationStatus = 'SUCCESS'): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
            '*/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => $verificationStatus,
            ]),
        ]);
    }

    protected function makePayment(string $gatewayReference): Payment
    {
        $tenant = Tenant::create([
            'name' => 'PayPal Webhook Co', 'slug' => 'paypal-webhook-co-'.uniqid(),
            'database_name' => 'tenant_paypal_webhook_'.uniqid(),
            'database_host' => config('database.connections.mysql.host'),
            'database_port' => config('database.connections.mysql.port'),
            'database_username' => config('database.connections.mysql.username'),
            'database_password' => config('database.connections.mysql.password'),
        ]);

        $plan = Plan::create([
            'name' => 'PayPal Plan', 'slug' => 'paypal-plan-'.uniqid(),
            'price' => 18, 'billing_interval' => 'monthly', 'trial_days' => 0,
        ]);

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => $plan->id,
            'status' => 'active', 'starts_at' => now(),
        ]);

        return Payment::create([
            'tenant_id' => $tenant->id, 'subscription_id' => $subscription->id,
            'amount' => 18, 'currency' => 'USD', 'payment_method' => 'paypal',
            'status' => 'pending', 'gateway_reference' => $gatewayReference,
        ]);
    }

    public function test_resolves_the_order_id_from_a_verified_event(): void
    {
        $this->fakePayPalHttp();

        $request = Request::create('/api/v1/webhooks/paypal', 'POST', content: json_encode([
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => 'ORDER-123'],
        ]));
        $request->headers->set('Content-Type', 'application/json');

        $reference = app(PayPalGateway::class)->resolveWebhookReference($request);

        $this->assertSame('ORDER-123', $reference);
    }

    public function test_rejects_an_event_paypal_could_not_verify(): void
    {
        $this->fakePayPalHttp('FAILURE');

        $request = Request::create('/api/v1/webhooks/paypal', 'POST', content: json_encode([
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => 'ORDER-123'],
        ]));
        $request->headers->set('Content-Type', 'application/json');

        $this->expectException(\RuntimeException::class);

        app(PayPalGateway::class)->resolveWebhookReference($request);
    }

    public function test_ignores_event_types_it_does_not_act_on(): void
    {
        $this->fakePayPalHttp();

        $request = Request::create('/api/v1/webhooks/paypal', 'POST', content: json_encode([
            'event_type' => 'PAYMENT.CAPTURE.DENIED',
            'resource' => ['id' => 'ORDER-123'],
        ]));
        $request->headers->set('Content-Type', 'application/json');

        $this->assertNull(app(PayPalGateway::class)->resolveWebhookReference($request));
    }

    public function test_webhook_endpoint_completes_the_matching_payment(): void
    {
        $payment = $this->makePayment('ORDER-END-TO-END');

        $this->fakePayPalHttp();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
            '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
            '*/v2/checkout/orders/ORDER-END-TO-END' => Http::response([
                'id' => 'ORDER-END-TO-END',
                'status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [['id' => 'CAPTURE-1']]]]],
            ]),
        ]);

        $this->postJson('/api/v1/webhooks/paypal', [
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => 'ORDER-END-TO-END'],
        ])->assertOk();

        $this->assertSame(PaymentStatus::COMPLETED, $payment->fresh()->status);
    }
}
