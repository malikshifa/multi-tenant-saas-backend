<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenantAdmins;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Notifications\Tenant\TrialExpired;
use Illuminate\Console\Command;
use Throwable;

class ExpireTrialSubscriptions extends Command
{
    use ResolvesTenantAdmins;

    protected $signature = 'subscriptions:expire-trials';

    protected $description = 'Expire trialing subscriptions whose trial period has ended without a completed payment';

    public function handle(): int
    {
        $subscriptions = Subscription::with('tenant')
            ->where('status', SubscriptionStatus::TRIALING)
            ->where('trial_ends_at', '<', now())
            ->whereDoesntHave('payments', function ($query) {
                $query->where('status', PaymentStatus::COMPLETED);
            })
            ->get();

        foreach ($subscriptions as $subscription) {
            $subscription->update(['status' => SubscriptionStatus::EXPIRED]);

            $this->info("Expired trial for subscription #{$subscription->id} (tenant: {$subscription->tenant->name}).");

            try {
                $this->withTenantAdmins($subscription->tenant, function ($admins) use ($subscription) {
                    foreach ($admins as $admin) {
                        $admin->notify(new TrialExpired($subscription));
                    }
                });
            } catch (Throwable $e) {
                $this->error("Could not notify tenant {$subscription->tenant->name}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
