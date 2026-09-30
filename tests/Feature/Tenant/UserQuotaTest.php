<?php

namespace Tests\Feature\Tenant;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\TenantProvisioningService;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = app(TenantService::class)->create(['name' => 'Quota Co']);
        $this->tenant->domains()->create(['domain' => 'quota-co.test']);
    }

    protected function tearDown(): void
    {
        app(TenantProvisioningService::class)->destroy($this->tenant);

        parent::tearDown();
    }

    protected function givePlanWithUserLimit(int $maxUsers): void
    {
        $plan = Plan::create([
            'name' => 'Solo', 'slug' => 'solo-'.uniqid(),
            'price' => 5, 'billing_interval' => 'monthly',
            'trial_days' => 0, 'max_users' => $maxUsers,
        ]);

        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
        ]);
    }

    public function test_registration_is_blocked_once_the_plan_user_limit_is_reached(): void
    {
        // The tenant already has one seeded Super Admin user, so a plan
        // capped at 1 user should reject any further registration.
        $this->givePlanWithUserLimit(1);

        $this->postJson('http://quota-co.test/api/v1/tenant/register', [
            'name' => 'Second User',
            'email' => 'second@quota-co.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422);
    }

    public function test_registration_succeeds_when_under_the_plan_user_limit(): void
    {
        $this->givePlanWithUserLimit(5);

        $this->postJson('http://quota-co.test/api/v1/tenant/register', [
            'name' => 'Second User',
            'email' => 'second@quota-co.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();
    }

    public function test_registration_is_unlimited_when_plan_has_no_max_users(): void
    {
        $plan = Plan::create([
            'name' => 'Unlimited', 'slug' => 'unlimited-'.uniqid(),
            'price' => 20, 'billing_interval' => 'monthly', 'trial_days' => 0,
        ]);

        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
        ]);

        $this->postJson('http://quota-co.test/api/v1/tenant/register', [
            'name' => 'Second User',
            'email' => 'second@quota-co.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();
    }
}
