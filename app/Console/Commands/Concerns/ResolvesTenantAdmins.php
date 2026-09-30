<?php

namespace App\Console\Commands\Concerns;

use App\Models\Tenant;
use App\Models\Tenant\User;
use App\Services\TenantDatabaseManager;
use Illuminate\Support\Collection;

/**
 * A console command runs outside any HTTP request, so no tenant is ever
 * connected when it starts. This briefly connects to one tenant's database
 * just long enough to resolve its admin users as notification recipients.
 */
trait ResolvesTenantAdmins
{
    protected function withTenantAdmins(Tenant $tenant, callable $callback): void
    {
        $databaseManager = app(TenantDatabaseManager::class);

        $databaseManager->connect($tenant);
        app()->instance('currentTenant', $tenant);

        try {
            $admins = User::role('Super Admin')->get();

            $callback($admins instanceof Collection ? $admins : collect($admins));
        } finally {
            $databaseManager->disconnect();
            app()->forgetInstance('currentTenant');
        }
    }
}
