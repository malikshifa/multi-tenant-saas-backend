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

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = app(TenantService::class)->create(['name' => 'Product Test Co']);
        $this->tenant->domains()->create(['domain' => 'product-test.test']);
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
            'email' => 'staff+'.uniqid().'@product-test.test',
            'password' => 'password',
        ]);

        foreach ($permissions as $permission) {
            $perm = Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'tenant']);
            $user->givePermissionTo($perm);
        }

        app()->forgetInstance('currentTenant');

        return $this->postJson('http://product-test.test/api/v1/tenant/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('data.token');
    }

    public function test_product_requires_unique_sku(): void
    {
        $token = $this->tokenWithPermissions(['products.create']);

        $this->withToken($token)->postJson('http://product-test.test/api/v1/tenant/products', [
            'name' => 'Widget',
            'sku' => 'WID-1',
            'price' => 9.99,
        ])->assertCreated();

        $this->withToken($token)->postJson('http://product-test.test/api/v1/tenant/products', [
            'name' => 'Widget 2',
            'sku' => 'WID-1',
            'price' => 12.99,
        ])->assertUnprocessable();
    }

    public function test_product_can_be_updated(): void
    {
        $token = $this->tokenWithPermissions(['products.create', 'products.update']);

        $id = $this->withToken($token)->postJson('http://product-test.test/api/v1/tenant/products', [
            'name' => 'Widget',
            'sku' => 'WID-2',
            'price' => 9.99,
            'stock_quantity' => 5,
        ])->json('data.id');

        $this->withToken($token)
            ->patchJson("http://product-test.test/api/v1/tenant/products/{$id}", ['price' => 14.99])
            ->assertOk()
            ->assertJsonPath('data.price', '14.99');
    }
}
