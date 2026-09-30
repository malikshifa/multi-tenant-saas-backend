<?php

namespace Tests\Feature\Platform;

use App\Models\Invoice;
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

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makePayment(): Payment
    {
        $tenant = Tenant::create([
            'name' => 'Invoice Co',
            'slug' => 'invoice-co',
            'database_name' => 'tenant_invoice_'.uniqid(),
            'database_host' => config('database.connections.mysql.host'),
            'database_port' => config('database.connections.mysql.port'),
            'database_username' => config('database.connections.mysql.username'),
            'database_password' => config('database.connections.mysql.password'),
        ]);

        $plan = Plan::create([
            'name' => 'Basic', 'slug' => 'basic-invoice', 'price' => 25,
            'billing_interval' => 'monthly', 'trial_days' => 0,
        ]);

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => $plan->id,
            'status' => 'active', 'starts_at' => now(),
        ]);

        return Payment::create([
            'tenant_id' => $tenant->id, 'subscription_id' => $subscription->id,
            'amount' => 25, 'currency' => 'USD', 'payment_method' => 'stripe',
            'status' => 'pending',
        ]);
    }

    protected function actingAsAdminWithInvoiceAccess(): User
    {
        $user = User::factory()->create();
        Permission::create(['name' => 'invoices.view', 'guard_name' => 'central']);
        $user->givePermissionTo('invoices.view');

        Sanctum::actingAs($user, ['*'], 'central');

        return $user;
    }

    public function test_an_invoice_is_created_automatically_when_a_payment_completes(): void
    {
        $payment = $this->makePayment();

        app(PaymentService::class)->markCompleted($payment, 'txn_invoice_1');

        $this->assertDatabaseHas('invoices', [
            'payment_id' => $payment->id,
            'invoice_number' => 'INV-'.str_pad($payment->id, 6, '0', STR_PAD_LEFT),
            'amount' => '25.00',
        ]);
    }

    public function test_invoice_list_and_show_are_permission_gated(): void
    {
        $payment = $this->makePayment();
        app(PaymentService::class)->markCompleted($payment, 'txn_invoice_2');

        $this->getJson('/api/v1/platform/invoices')->assertUnauthorized();

        $this->actingAsAdminWithInvoiceAccess();

        $this->getJson('/api/v1/platform/invoices')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $invoiceId = Invoice::first()->id;

        $this->getJson("/api/v1/platform/invoices/{$invoiceId}")
            ->assertOk()
            ->assertJsonPath('data.invoice_number', 'INV-'.str_pad($payment->id, 6, '0', STR_PAD_LEFT));
    }

    public function test_invoice_print_view_renders(): void
    {
        $payment = $this->makePayment();
        app(PaymentService::class)->markCompleted($payment, 'txn_invoice_3');

        $this->actingAsAdminWithInvoiceAccess();

        $invoiceId = Invoice::first()->id;

        $this->get("/api/v1/platform/invoices/{$invoiceId}/view")
            ->assertOk()
            ->assertSee('Invoice');
    }
}
