<?php

namespace Tests\Feature\Platform;

use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\TenantProvisioningService;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function makePlan(): Plan
    {
        return Plan::create([
            'name' => 'Trial Plan', 'slug' => 'trial-plan-'.uniqid(),
            'price' => 10, 'billing_interval' => 'monthly', 'trial_days' => 7,
        ]);
    }

    public function test_expired_trial_without_payment_is_marked_expired(): void
    {
        $tenant = app(TenantService::class)->create(['name' => 'Trial Expiry Co']);
        $plan = $this->makePlan();

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => $plan->id,
            'status' => SubscriptionStatus::TRIALING,
            'starts_at' => now()->subDays(10),
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:expire-trials')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::EXPIRED, $subscription->fresh()->status);

        app(TenantProvisioningService::class)->destroy($tenant);
    }

    public function test_trial_with_a_completed_payment_is_not_expired(): void
    {
        $tenant = app(TenantService::class)->create(['name' => 'Trial Paid Co']);
        $plan = $this->makePlan();

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => $plan->id,
            'status' => SubscriptionStatus::TRIALING,
            'starts_at' => now()->subDays(10),
            'trial_ends_at' => now()->subDay(),
        ]);

        Payment::create([
            'tenant_id' => $tenant->id, 'subscription_id' => $subscription->id,
            'amount' => 10, 'currency' => 'USD', 'payment_method' => 'stripe',
            'status' => 'completed', 'paid_at' => now(),
        ]);

        $this->artisan('subscriptions:expire-trials')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::TRIALING, $subscription->fresh()->status);

        app(TenantProvisioningService::class)->destroy($tenant);
    }

    public function test_future_trial_is_untouched(): void
    {
        $tenant = app(TenantService::class)->create(['name' => 'Trial Future Co']);
        $plan = $this->makePlan();

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => $plan->id,
            'status' => SubscriptionStatus::TRIALING,
            'starts_at' => now(),
            'trial_ends_at' => now()->addDays(5),
        ]);

        $this->artisan('subscriptions:expire-trials')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::TRIALING, $subscription->fresh()->status);

        app(TenantProvisioningService::class)->destroy($tenant);
    }
}
