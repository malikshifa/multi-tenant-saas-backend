<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Services\TenantDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class TenantMigrate extends Command
{
    protected $signature = 'tenants:migrate';

    protected $description = 'Run tenant migrations for all active tenants';

    public function handle(TenantDatabaseManager $databaseManager): int
    {
        $failed = 0;

        Tenant::query()
            ->where('status', TenantStatus::ACTIVE)
            ->each(function (Tenant $tenant) use ($databaseManager, &$failed) {
                $this->info("Migrating: {$tenant->name}");

                try {
                    $databaseManager->connect($tenant);

                    $exitCode = Artisan::call('migrate', [
                        '--database' => 'tenant',
                        '--path' => 'database/migrations/tenant',
                        '--force' => true,
                    ]);

                    $this->line(Artisan::output());

                    if ($exitCode !== self::SUCCESS) {
                        throw new \RuntimeException("migrate exited with code {$exitCode}");
                    }
                } catch (Throwable $e) {
                    $failed++;

                    $this->error("Failed: {$tenant->name}");
                    $this->error($e->getMessage());
                } finally {
                    $databaseManager->disconnect();
                }
            });

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
