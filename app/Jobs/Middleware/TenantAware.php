<?php

namespace App\Jobs\Middleware;

use App\Models\Tenant;
use App\Services\TenantDatabaseManager;
use App\Support\PermissionCacheKey;

/**
 * A queue worker is a long-lived process with no per-request tenant context.
 * Without this, a job touching tenant-connection models would either run
 * against whichever tenant DB the previous job in this worker left
 * configured, or against none at all. This reconnects the correct tenant
 * DB around the job exactly like IdentifyTenant does for an HTTP request.
 */
class TenantAware
{
    public function __construct(
        protected int $tenantId
    ) {}

    public function handle(object $job, callable $next): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);

        app(TenantDatabaseManager::class)->connect($tenant);
        app()->instance('currentTenant', $tenant);

        $defaultCacheKey = PermissionCacheKey::useForTenant($tenant->id);

        try {
            $next($job);
        } finally {
            app(TenantDatabaseManager::class)->disconnect();
            app()->forgetInstance('currentTenant');

            PermissionCacheKey::restore($defaultCacheKey);
        }
    }
}
