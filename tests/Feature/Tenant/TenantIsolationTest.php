<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioningService;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = app(TenantService::class)->create(['name' => 'Acme Test']);
        $this->tenant->domains()->create(['domain' => 'acme.test']);
    }

    protected function tearDown(): void
    {
        app(TenantProvisioningService::class)->destroy($this->tenant);

        parent::tearDown();
    }

    protected function loginAsTenantAdmin(): string
    {
        return $this->postJson('http://acme.test/api/v1/tenant/login', [
            'email' => 'superAdmin@ptenant',
            'password' => 'password',
        ])->assertOk()->json('data.token');
    }

    public function test_tenant_admin_gets_permissions_from_the_tenant_database(): void
    {
        $token = $this->loginAsTenantAdmin();

        $this->withToken($token)
            ->getJson('http://acme.test/api/v1/tenant/me')
            ->assertOk()
            ->assertJsonPath('data.roles.0', 'Super Admin')
            ->assertJsonFragment(['roles.view']);
    }

    public function test_tenant_registration_uniqueness_is_checked_in_the_tenant_database(): void
    {
        User::factory()->create(['email' => 'shared@example.com']);

        $this->postJson('http://acme.test/api/v1/tenant/register', [
            'name' => 'Jane',
            'email' => 'shared@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();
    }

    public function test_duplicate_tenant_names_get_unique_slugs(): void
    {
        $second = app(TenantService::class)->create(['name' => 'Acme Test']);

        try {
            $this->assertNotSame($this->tenant->slug, $second->slug);
        } finally {
            app(TenantProvisioningService::class)->destroy($second);
        }
    }

    public function test_suspended_tenant_is_blocked(): void
    {
        app(TenantService::class)->suspend($this->tenant);

        $this->postJson('http://acme.test/api/v1/tenant/login', [
            'email' => 'superAdmin@ptenant',
            'password' => 'password',
        ])->assertForbidden();
    }
}
