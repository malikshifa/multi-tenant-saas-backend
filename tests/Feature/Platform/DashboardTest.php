<?php

namespace Tests\Feature\Platform;

use App\Models\Payment;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summary_reports_accurate_counts(): void
    {
        $user = User::factory()->create();
        Permission::create(['name' => 'dashboard.view', 'guard_name' => 'central']);
        $user->givePermissionTo('dashboard.view');
        Sanctum::actingAs($user, ['*'], 'central');

        $tenant = Tenant::create([
            'name' => 'Dashboard Co', 'slug' => 'dashboard-co',
            'status' => 'active',
            'database_name' => 'tenant_dash_'.uniqid(),
            'database_host' => config('database.connections.mysql.host'),
            'database_port' => config('database.connections.mysql.port'),
            'database_username' => config('database.connections.mysql.username'),
            'database_password' => config('database.connections.mysql.password'),
        ]);

        $plan = Plan::create([
            'name' => 'Dash Plan', 'slug' => 'dash-plan',
            'price' => 30, 'billing_interval' => 'monthly', 'trial_days' => 0,
        ]);

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => $plan->id,
            'status' => 'active', 'starts_at' => now(),
        ]);

        $payment = Payment::create([
            'tenant_id' => $tenant->id, 'subscription_id' => $subscription->id,
            'amount' => 30, 'currency' => 'USD', 'payment_method' => 'stripe',
            'status' => 'pending',
        ]);

        app(PaymentService::class)->markCompleted($payment, 'txn_dash_1');

        $response = $this->getJson('/api/v1/platform/dashboard/summary')->assertOk();

        $this->assertSame(1, $response->json('data.tenants_by_status.active'));
        $this->assertSame(1, $response->json('data.subscriptions_by_status.active'));
        $this->assertEquals(30, $response->json('data.revenue_this_month'));
    }
}
