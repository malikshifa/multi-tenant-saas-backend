<?php

namespace Tests\Feature\Platform;

use App\Jobs\ProvisionTenantJob;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Platform\PaymentCompleted;
use App\Notifications\Platform\TenantProvisioned;
use App\Services\PaymentService;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_with_permission_are_notified_when_a_tenant_finishes_provisioning(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        Permission::create(['name' => 'tenants.view', 'guard_name' => 'central']);
        $admin->givePermissionTo('tenants.view');

        $tenant = Tenant::create([
            'name' => 'Notify Co',
            'slug' => 'notify-co',
            'database_name' => 'tenant_notify_'.uniqid(),
            'database_host' => config('database.connections.mysql.host'),
            'database_port' => config('database.connections.mysql.port'),
            'database_username' => config('database.connections.mysql.username'),
            'database_password' => config('database.connections.mysql.password'),
        ]);

        ProvisionTenantJob::dispatchSync($tenant->id);

        Notification::assertSentTo($admin, TenantProvisioned::class);

        app(TenantProvisioningService::class)->destroy($tenant->fresh());
    }

    public function test_admins_with_permission_are_notified_when_a_payment_completes(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        Permission::create(['name' => 'payments.view', 'guard_name' => 'central']);
        $admin->givePermissionTo('payments.view');

        $tenant = Tenant::create([
            'name' => 'Payment Notify Co',
            'slug' => 'payment-notify-co',
            'database_name' => 'tenant_pay_'.uniqid(),
            'database_host' => config('database.connections.mysql.host'),
            'database_port' => config('database.connections.mysql.port'),
            'database_username' => config('database.connections.mysql.username'),
            'database_password' => config('database.connections.mysql.password'),
        ]);
        $plan = Plan::create([
            'name' => 'Basic', 'slug' => 'basic', 'price' => 10,
            'billing_interval' => 'monthly', 'trial_days' => 0,
        ]);
        $subscription = Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => $plan->id,
            'status' => 'active', 'starts_at' => now(),
        ]);
        $payment = Payment::create([
            'tenant_id' => $tenant->id, 'subscription_id' => $subscription->id,
            'amount' => 10, 'currency' => 'USD', 'payment_method' => 'stripe',
            'status' => 'pending',
        ]);

        app(PaymentService::class)->markCompleted($payment, 'txn_123');

        Notification::assertSentTo($admin, PaymentCompleted::class);
    }

    public function test_user_can_list_and_mark_notifications_as_read(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*'], 'central');

        $tenant = Tenant::create([
            'name' => 'List Notify Co',
            'slug' => 'list-notify-co',
            'database_name' => 'tenant_list_'.uniqid(),
            'database_host' => config('database.connections.mysql.host'),
            'database_port' => config('database.connections.mysql.port'),
            'database_username' => config('database.connections.mysql.username'),
            'database_password' => config('database.connections.mysql.password'),
        ]);
        $user->notify(new TenantProvisioned($tenant));

        $list = $this->getJson('/api/v1/platform/notifications')->assertOk();
        $id = $list->json('data.0.id');

        $this->assertNull($list->json('data.0.read_at'));

        $this->postJson("/api/v1/platform/notifications/{$id}/read")->assertOk();

        $this->getJson('/api/v1/platform/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.read_at', fn ($value) => $value !== null);
    }
}
