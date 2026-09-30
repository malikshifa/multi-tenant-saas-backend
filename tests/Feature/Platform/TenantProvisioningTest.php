<?php

namespace Tests\Feature\Platform;

use App\Enums\TenantStatus;
use App\Jobs\ProvisionTenantJob;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsCentralAdmin(): User
    {
        $user = User::factory()->create();

        Permission::create(['name' => 'tenants.create', 'guard_name' => 'central']);
        $user->givePermissionTo('tenants.create');

        Sanctum::actingAs($user, ['*'], 'central');

        return $user;
    }

    public function test_creating_a_tenant_responds_immediately_without_waiting_for_provisioning(): void
    {
        Queue::fake();

        $this->actingAsCentralAdmin();

        $response = $this->postJson('/api/v1/platform/tenants', ['name' => 'Async Co'])
            ->assertStatus(202)
            ->assertJsonPath('data.status', TenantStatus::PROVISIONING->value);

        Queue::assertPushed(ProvisionTenantJob::class, function (ProvisionTenantJob $job) use ($response) {
            return $job->tenantId === $response->json('data.id');
        });
    }

    public function test_tenant_becomes_active_once_the_provisioning_job_runs(): void
    {
        $this->actingAsCentralAdmin();

        $tenantId = $this->postJson('/api/v1/platform/tenants', ['name' => 'Sync Co'])
            ->assertStatus(202)
            ->json('data.id');

        // phpunit.xml runs the queue synchronously, so by the time the HTTP
        // response above returned, ProvisionTenantJob has already executed.
        $tenant = Tenant::findOrFail($tenantId);

        $this->assertSame(TenantStatus::ACTIVE, $tenant->status);
        $this->assertNotNull($tenant->provisioned_at);

        app(TenantProvisioningService::class)->destroy($tenant);
    }
}
