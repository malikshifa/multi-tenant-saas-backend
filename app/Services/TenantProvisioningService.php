<?php

namespace App\Services;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class TenantProvisioningService
{
    public function __construct(
        protected TenantDatabaseManager $databaseManager
    ) {}

    public function provision(Tenant $tenant): void
    {
        try {
            $tenant->update([
                'status' => TenantStatus::PROVISIONING,
            ]);

            $this->databaseManager->createDatabase($tenant);

            $this->databaseManager->connect($tenant);

            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            Artisan::call('db:seed', [
                '--database' => 'tenant',
                '--class' => 'Database\\Seeders\\Tenant\\DatabaseSeeder',
                '--force' => true,
            ]);

            $tenant->update([
                'status' => TenantStatus::ACTIVE,
                'provisioned_at' => now(),
            ]);
        } catch (Throwable $e) {
            $tenant->update([
                'status' => TenantStatus::FAILED,
            ]);

            $this->databaseManager->disconnect();
            rescue(fn () => $this->databaseManager->dropDatabase($tenant));

            throw $e;
        } finally {
            $this->databaseManager->disconnect();
        }
    }

    public function destroy(Tenant $tenant): void
    {
        $this->databaseManager->disconnect();

        $this->databaseManager->dropDatabase($tenant);
    }
}
