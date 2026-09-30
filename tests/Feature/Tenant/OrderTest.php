<?php

namespace Tests\Feature\Tenant;

use App\Models\Permission;
use App\Models\Tenant;
use App\Models\Tenant\User;
use App\Services\TenantDatabaseManager;
use App\Services\TenantProvisioningService;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected string $token;

    protected int $customerId;

    protected int $productId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = app(TenantService::class)->create(['name' => 'Order Test Co']);
        $this->tenant->domains()->create(['domain' => 'order-test.test']);

        $this->token = $this->tokenWithPermissions([
            'customers.create', 'products.create', 'products.view', 'orders.view', 'orders.create', 'orders.cancel',
        ]);

        $this->customerId = $this->withToken($this->token)->postJson('http://order-test.test/api/v1/tenant/customers', [
            'name' => 'Jane Buyer',
        ])->json('data.id');

        $this->productId = $this->withToken($this->token)->postJson('http://order-test.test/api/v1/tenant/products', [
            'name' => 'Widget',
            'sku' => 'WID-ORDER-1',
            'price' => 10.00,
            'stock_quantity' => 5,
        ])->json('data.id');
    }

    protected function tearDown(): void
    {
        app(TenantProvisioningService::class)->destroy($this->tenant);

        parent::tearDown();
    }

    protected function tokenWithPermissions(array $permissions): string
    {
        app(TenantDatabaseManager::class)->connect($this->tenant);
        app()->instance('currentTenant', $this->tenant);

        $user = User::create([
            'name' => 'Staff',
            'email' => 'staff+'.uniqid().'@order-test.test',
            'password' => 'password',
        ]);

        foreach ($permissions as $permission) {
            $perm = Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'tenant']);
            $user->givePermissionTo($perm);
        }

        app()->forgetInstance('currentTenant');

        return $this->postJson('http://order-test.test/api/v1/tenant/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('data.token');
    }

    public function test_order_total_and_stock_are_computed_correctly(): void
    {
        $response = $this->withToken($this->token)->postJson('http://order-test.test/api/v1/tenant/orders', [
            'customer_id' => $this->customerId,
            'items' => [
                ['product_id' => $this->productId, 'quantity' => 3],
            ],
        ])->assertCreated();

        $response->assertJsonPath('data.total_amount', '30.00');

        $this->withToken($this->token)
            ->getJson("http://order-test.test/api/v1/tenant/products/{$this->productId}")
            ->assertJsonPath('data.stock_quantity', 2);
    }

    public function test_order_rejects_insufficient_stock(): void
    {
        $this->withToken($this->token)->postJson('http://order-test.test/api/v1/tenant/orders', [
            'customer_id' => $this->customerId,
            'items' => [
                ['product_id' => $this->productId, 'quantity' => 999],
            ],
        ])->assertStatus(422);

        $this->withToken($this->token)
            ->getJson("http://order-test.test/api/v1/tenant/products/{$this->productId}")
            ->assertJsonPath('data.stock_quantity', 5);
    }

    public function test_cancelling_an_order_restocks_items(): void
    {
        $orderId = $this->withToken($this->token)->postJson('http://order-test.test/api/v1/tenant/orders', [
            'customer_id' => $this->customerId,
            'items' => [
                ['product_id' => $this->productId, 'quantity' => 2],
            ],
        ])->json('data.id');

        $this->withToken($this->token)
            ->postJson("http://order-test.test/api/v1/tenant/orders/{$orderId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->withToken($this->token)
            ->getJson("http://order-test.test/api/v1/tenant/products/{$this->productId}")
            ->assertJsonPath('data.stock_quantity', 5);
    }
}
