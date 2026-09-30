<?php

namespace App\Http\Controllers\Platform;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function summary()
    {
        return response()->json([
            'data' => [
                'tenants_by_status' => Tenant::query()
                    ->selectRaw('status, count(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status'),

                'subscriptions_by_status' => Subscription::query()
                    ->selectRaw('status, count(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status'),

                'revenue_this_month' => Payment::query()
                    ->where('status', PaymentStatus::COMPLETED)
                    ->whereMonth('paid_at', now()->month)
                    ->whereYear('paid_at', now()->year)
                    ->sum('amount'),

                'failed_jobs' => Schema::hasTable('failed_jobs')
                    ? DB::table('failed_jobs')->count()
                    : 0,

                'recent_activity_count' => Schema::hasTable('activity_log')
                    ? Activity::query()->where('created_at', '>=', now()->subDays(7))->count()
                    : null,
            ],
        ]);
    }
}
