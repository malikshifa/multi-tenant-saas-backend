<?php

namespace App\Models\Concerns;

trait ResolvesTenantConnection
{
    public function getConnectionName(): ?string
    {
        return app()->bound('currentTenant')
            ? 'tenant'
            : config('database.default');
    }
}
