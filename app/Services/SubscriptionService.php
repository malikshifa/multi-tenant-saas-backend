<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

class SubscriptionService
{
    public function create(array $data): Subscription
    {
        $tenant = Tenant::findOrFail($data['tenant_id']);

        $plan = Plan::where('id', $data['plan_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $startsAt = isset($data['starts_at'])
            ? Carbon::parse($data['starts_at'])
            : now();

        $trialEndsAt = null;
        $endsAt = null;

        if ($plan->trial_days > 0) {
            $trialEndsAt = $startsAt->copy()
                ->addDays($plan->trial_days);
        }

        $endsAt = match ($plan->billing_interval->value) {
            'monthly' => $startsAt->copy()->addMonth(),
            'yearly' => $startsAt->copy()->addYear(),
            default => null,
        };

        $status = $plan->trial_days > 0
            ? SubscriptionStatus::TRIALING
            : SubscriptionStatus::ACTIVE;

        return Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => $status,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'trial_ends_at' => $trialEndsAt,
        ]);
    }

    public function update(
        Subscription $subscription,
        array $data
    ): Subscription {
        if (isset($data['plan_id'])) {
            $plan = Plan::where('id', $data['plan_id'])
                ->where('is_active', true)
                ->firstOrFail();

            $subscription->plan_id = $plan->id;
        }

        if (isset($data['starts_at'])) {
            $subscription->starts_at = Carbon::parse(
                $data['starts_at']
            );
        }

        $subscription->save();

        return $subscription->fresh();
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->update([
            'status' => SubscriptionStatus::CANCELLED,
            'cancelled_at' => now(),
        ]);

        return $subscription->fresh();
    }
}
