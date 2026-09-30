<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Platform\TenantProvisioned;
use App\Notifications\Platform\TenantProvisioningFailed;
use App\Services\TenantProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Throwable;

/**
 * Runs tenant provisioning (create DB, migrate, seed) off the request cycle,
 * so tenant creation responds immediately instead of blocking on it. Does
 * NOT use the TenantAware job middleware: that middleware assumes the
 * tenant's database already exists and is connectable, which isn't true
 * until this job's own work finishes. It manages the tenant connection
 * itself, exactly as TenantProvisioningService already did synchronously.
 */
class ProvisionTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $tenantId
    ) {}

    public function handle(TenantProvisioningService $provisioning): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);

        try {
            $provisioning->provision($tenant);
        } catch (Throwable $e) {
            $this->notifyAdmins(new TenantProvisioningFailed($tenant, $e->getMessage()));

            throw $e;
        }

        $this->notifyAdmins(new TenantProvisioned($tenant->fresh()));
    }

    protected function notifyAdmins($notification): void
    {
        try {
            Notification::send(User::permission('tenants.view')->get(), $notification);
        } catch (PermissionDoesNotExist) {
            // Nothing to notify yet.
        }
    }
}
