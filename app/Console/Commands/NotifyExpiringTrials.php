<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenantAdmins;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Notifications\Tenant\TrialExpiringSoon;
use Illuminate\Console\Command;
use Throwable;

class NotifyExpiringTrials extends Command
{
    use ResolvesTenantAdmins;

    protected $signature = 'subscriptions:notify-expiring-trials {--days=3 : How many days ahead of expiry to notify}';

    protected $description = 'Notify tenants whose trial ends within the given number of days';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $subscriptions = Subscription::with('tenant')
            ->where('status', SubscriptionStatus::TRIALING)
            ->whereNull('trial_expiry_notified_at')
            ->whereBetween('trial_ends_at', [now(), now()->addDays($days)])
            ->get();

        foreach ($subscriptions as $subscription) {
            try {
                $this->withTenantAdmins($subscription->tenant, function ($admins) use ($subscription) {
                    foreach ($admins as $admin) {
                        $admin->notify(new TrialExpiringSoon($subscription));
                    }
                });

                $subscription->update(['trial_expiry_notified_at' => now()]);

                $this->info("Notified tenant {$subscription->tenant->name} about its expiring trial.");
            } catch (Throwable $e) {
                $this->error("Could not notify tenant {$subscription->tenant->name}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
