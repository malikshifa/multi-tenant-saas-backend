<?php

namespace App\Services\Tenant;

use App\Models\Tenant;
use App\Models\Tenant\User;
use RuntimeException;

class TenantUserQuotaService
{
    public function assertCapacityAvailable(Tenant $tenant): void
    {
        $plan = $tenant->activeSubscription?->plan;
        $max = $plan?->max_users;

        if ($max === null) {
            return;
        }

        if (User::count() >= $max) {
            throw new RuntimeException(
                "This plan allows a maximum of {$max} user(s). Upgrade your plan to add more."
            );
        }
    }
}
