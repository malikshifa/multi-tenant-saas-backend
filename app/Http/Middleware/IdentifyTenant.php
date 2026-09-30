<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Models\Domain;
use App\Services\TenantDatabaseManager;
use App\Support\PermissionCacheKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function __construct(
        protected TenantDatabaseManager $databaseManager
    ) {}

    public function handle(
        Request $request,
        Closure $next
    ): Response {

        $host = $request->getHost();

        $domain = Domain::with('tenant')
            ->where('domain', $host)
            ->first();

        if (! $domain) {
            return response()->json([
                'message' => 'Tenant not found.',
            ], 404);
        }

        $tenant = $domain->tenant;

        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant not found.',
            ], 404);
        }

        if ($tenant->status !== TenantStatus::ACTIVE) {
            return response()->json([
                'message' => 'Tenant is not active.',
            ], 403);
        }

        $this->databaseManager->connect($tenant);

        $request->attributes->set('tenant', $tenant);

        app()->instance('currentTenant', $tenant);

        $defaultCacheKey = PermissionCacheKey::useForTenant($tenant->id);

        try {
            return $next($request);
        } finally {
            $this->databaseManager->disconnect();
            app()->forgetInstance('currentTenant');

            PermissionCacheKey::restore($defaultCacheKey);
        }
    }
}
