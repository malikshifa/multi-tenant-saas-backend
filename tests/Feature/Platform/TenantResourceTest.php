<?php

namespace Tests\Feature\Platform;

use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioningService;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantResourceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = app(TenantService::class)->create(['name' => 'Secret Co']);
    }

    protected function tearDown(): void
    {
        app(TenantProvisioningService::class)->destroy($this->tenant);

        parent::tearDown();
    }

    protected function actingAsCentralAdmin(): User
    {
        $user = User::factory()->create();

        Permission::create(['name' => 'tenants.view', 'guard_name' => 'central']);
        $user->givePermissionTo('tenants.view');

        Sanctum::actingAs($user, ['*'], 'central');

        return $user;
    }

    protected function assertNoDatabaseCredentialsLeak(array $payload): void
    {
        $flat = json_encode($payload);

        foreach (['database_host', 'database_port', 'database_username', 'database_password'] as $key) {
            $this->assertStringNotContainsString($key, $flat, "Response leaked the '{$key}' field.");
        }
    }

    public function test_tenant_index_never_exposes_database_credentials(): void
    {
        $this->actingAsCentralAdmin();

        $response = $this->getJson('/api/v1/platform/tenants')->assertOk();

        $this->assertNoDatabaseCredentialsLeak($response->json());
    }

    public function test_tenant_show_never_exposes_database_credentials(): void
    {
        $this->actingAsCentralAdmin();

        $response = $this->getJson("/api/v1/platform/tenants/{$this->tenant->id}")->assertOk();

        $this->assertNoDatabaseCredentialsLeak($response->json());
    }
}
