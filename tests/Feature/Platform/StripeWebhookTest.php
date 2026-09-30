<?php

namespace Tests\Feature\Platform;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\Gateways\StripeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Stripe\WebhookSignature;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function makePayment(string $gatewayReference): Payment
    {
        $tenant = Tenant::create([
            'name' => 'Webhook Co', 'slug' => 'webhook-co-'.uniqid(),
            'database_name' => 'tenant_webhook_'.uniqid(),
            'database_host' => config('database.connections.mysql.host'),
            'database_port' => config('database.connections.mysql.port'),
            'database_username' => config('database.connections.mysql.username'),
            'database_password' => config('database.connections.mysql.password'),
        ]);

        $plan = Plan::create([
            'name' => 'Webhook Plan', 'slug' => 'webhook-plan-'.uniqid(),
            'price' => 12, 'billing_interval' => 'monthly', 'trial_days' => 0,
        ]);

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => $plan->id,
            'status' => 'active', 'starts_at' => now(),
        ]);

        return Payment::create([
            'tenant_id' => $tenant->id, 'subscription_id' => $subscription->id,
            'amount' => 12, 'currency' => 'USD', 'payment_method' => 'stripe',
            'status' => 'pending', 'gateway_reference' => $gatewayReference,
        ]);
    }

    protected function signedPayload(array $body): array
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret']);

        $payload = json_encode($body);
        $header = WebhookSignature::generateSignatureHeader($payload, 'whsec_test_secret');

        return [$payload, $header];
    }

    public function test_resolves_the_checkout_session_id_from_a_validly_signed_event(): void
    {
        [$payload, $header] = $this->signedPayload([
            'id' => 'evt_1',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_test_123', 'object' => 'checkout.session']],
        ]);

        $request = Request::create('/api/v1/webhooks/stripe', 'POST', content: $payload);
        $request->headers->set('Stripe-Signature', $header);

        $reference = app(StripeGateway::class)->resolveWebhookReference($request);

        $this->assertSame('cs_test_123', $reference);
    }

    public function test_rejects_a_badly_signed_event(): void
    {
        $payload = json_encode(['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_123']]]);

        $request = Request::create('/api/v1/webhooks/stripe', 'POST', content: $payload);
        $request->headers->set('Stripe-Signature', 't=1,v1=not-a-real-signature');

        $this->expectException(\RuntimeException::class);

        app(StripeGateway::class)->resolveWebhookReference($request);
    }

    public function test_ignores_event_types_it_does_not_act_on(): void
    {
        [$payload, $header] = $this->signedPayload([
            'id' => 'evt_1',
            'type' => 'payment_intent.created',
            'data' => ['object' => ['id' => 'pi_test_123']],
        ]);

        $request = Request::create('/api/v1/webhooks/stripe', 'POST', content: $payload);
        $request->headers->set('Stripe-Signature', $header);

        $this->assertNull(app(StripeGateway::class)->resolveWebhookReference($request));
    }

    public function test_webhook_endpoint_completes_the_matching_payment(): void
    {
        $payment = $this->makePayment('cs_test_end_to_end');

        $fake = new class implements PaymentGatewayInterface
        {
            public function createCheckout(Payment $payment): array
            {
                return [];
            }

            public function verify(Payment $payment): array
            {
                return ['status' => 'paid', 'transaction_id' => 'pi_fake_123'];
            }

            public function refund(Payment $payment): array
            {
                return [];
            }

            public function resolveWebhookReference(Request $request): ?string
            {
                return 'cs_test_end_to_end';
            }
        };

        $this->app->bind(StripeGateway::class, fn () => $fake);

        $this->postJson('/api/v1/webhooks/stripe', ['type' => 'checkout.session.completed'])
            ->assertOk();

        $this->assertSame(PaymentStatus::COMPLETED, $payment->fresh()->status);
    }

    public function test_webhook_endpoint_acknowledges_unknown_payments_without_erroring(): void
    {
        $fake = new class implements PaymentGatewayInterface
        {
            public function createCheckout(Payment $payment): array
            {
                return [];
            }

            public function verify(Payment $payment): array
            {
                return [];
            }

            public function refund(Payment $payment): array
            {
                return [];
            }

            public function resolveWebhookReference(Request $request): ?string
            {
                return 'cs_does_not_exist';
            }
        };

        $this->app->bind(StripeGateway::class, fn () => $fake);

        $this->postJson('/api/v1/webhooks/stripe', ['type' => 'checkout.session.completed'])
            ->assertOk();
    }
}
