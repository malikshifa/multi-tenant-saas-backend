<?php

namespace Tests\Unit\Jobs;

use App\Jobs\Concerns\TenantAwareJob;
use App\Services\TenantProvisioningService;
use App\Services\TenantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProbeJob implements ShouldQueue
{
    use Dispatchable, Queueable, TenantAwareJob;

    public static array $captured = [];

    public function __construct(public int $tenantId) {}

    public function handle(): void
    {
        static::$captured[] = [
            'tenant_id' => $this->tenantId,
            'current_tenant_bound' => app()->bound('currentTenant'),
            'current_tenant_id' => app()->bound('currentTenant') ? app('currentTenant')->id : null,
            'tenant_database' => DB::connection('tenant')->getDatabaseName(),
        ];
    }
}

class TenantAwareJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_middleware_connects_the_correct_tenant_and_cleans_up_after(): void
    {
        ProbeJob::$captured = [];

        $tenant = app(TenantService::class)->create(['name' => 'Job Test Co']);

        $this->assertFalse(app()->bound('currentTenant'));

        ProbeJob::dispatchSync($tenant->id);

        $this->assertFalse(app()->bound('currentTenant'));

        $this->assertCount(1, ProbeJob::$captured);
        $this->assertTrue(ProbeJob::$captured[0]['current_tenant_bound']);
        $this->assertSame($tenant->id, ProbeJob::$captured[0]['current_tenant_id']);
        $this->assertSame($tenant->database_name, ProbeJob::$captured[0]['tenant_database']);

        app(TenantProvisioningService::class)->destroy($tenant);
    }

    public function test_back_to_back_jobs_for_different_tenants_never_cross_connections(): void
    {
        ProbeJob::$captured = [];

        $tenantA = app(TenantService::class)->create(['name' => 'Tenant A']);
        $tenantB = app(TenantService::class)->create(['name' => 'Tenant B']);

        ProbeJob::dispatchSync($tenantA->id);
        ProbeJob::dispatchSync($tenantB->id);

        [$resultA, $resultB] = ProbeJob::$captured;

        $this->assertSame($tenantA->database_name, $resultA['tenant_database']);
        $this->assertSame($tenantB->database_name, $resultB['tenant_database']);
        $this->assertNotSame($resultA['tenant_database'], $resultB['tenant_database']);

        app(TenantProvisioningService::class)->destroy($tenantA);
        app(TenantProvisioningService::class)->destroy($tenantB);
    }
}
