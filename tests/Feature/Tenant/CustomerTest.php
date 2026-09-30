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

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = app(TenantService::class)->create(['name' => 'Customer Test Co']);
        $this->tenant->domains()->create(['domain' => 'customer-test.test']);
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
            'email' => 'staff+'.uniqid().'@customer-test.test',
            'password' => 'password',
        ]);

        foreach ($permissions as $permission) {
            $perm = Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'tenant']);
            $user->givePermissionTo($perm);
        }

        app()->forgetInstance('currentTenant');

        return $this->postJson('http://customer-test.test/api/v1/tenant/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('data.token');
    }

    public function test_user_without_permission_cannot_list_customers(): void
    {
        $token = $this->tokenWithPermissions([]);

        $this->withToken($token)
            ->getJson('http://customer-test.test/api/v1/tenant/customers')
            ->assertForbidden();
    }

    public function test_user_with_permission_can_create_and_list_customers(): void
    {
        $token = $this->tokenWithPermissions(['customers.view', 'customers.create']);

        $this->withToken($token)
            ->postJson('http://customer-test.test/api/v1/tenant/customers', [
                'name' => 'Acme Corp',
                'email' => 'contact@acme.test',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Acme Corp');

        $this->withToken($token)
            ->getJson('http://customer-test.test/api/v1/tenant/customers')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_customers_are_isolated_between_tenants(): void
    {
        $token = $this->tokenWithPermissions(['customers.view', 'customers.create']);

        $this->withToken($token)->postJson('http://customer-test.test/api/v1/tenant/customers', [
            'name' => 'Tenant A Customer',
        ])->assertCreated();

        $otherTenant = app(TenantService::class)->create(['name' => 'Other Tenant']);
        $otherTenant->domains()->create(['domain' => 'other-tenant.test']);

        try {
            app(TenantDatabaseManager::class)->connect($otherTenant);
            app()->instance('currentTenant', $otherTenant);
            $otherUser = User::create([
                'name' => 'Other Staff',
                'email' => 'staff@other-tenant.test',
                'password' => 'password',
            ]);
            $perm = Permission::firstOrCreate(['name' => 'customers.view', 'guard_name' => 'tenant']);
            $otherUser->givePermissionTo($perm);
            app()->forgetInstance('currentTenant');

            $otherToken = $this->postJson('http://other-tenant.test/api/v1/tenant/login', [
                'email' => $otherUser->email,
                'password' => 'password',
            ])->json('data.token');

            $this->withToken($otherToken)
                ->getJson('http://other-tenant.test/api/v1/tenant/customers')
                ->assertOk()
                ->assertJsonCount(0, 'data');
        } finally {
            app(TenantProvisioningService::class)->destroy($otherTenant);
        }
    }
}
