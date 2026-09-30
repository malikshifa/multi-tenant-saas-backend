<?php

namespace App\Jobs\Concerns;

use App\Jobs\Middleware\TenantAware;

/**
 * For any job whose handle() touches tenant-connection models. The job's
 * constructor must accept and store `int $tenantId` (a plain id, not a
 * Tenant model, so the job stays queue-serializable).
 */
trait TenantAwareJob
{
    public function middleware(): array
    {
        return [new TenantAware($this->tenantId)];
    }
}
