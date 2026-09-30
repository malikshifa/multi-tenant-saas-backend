<?php

namespace App\Http\Controllers\Platform;

use App\Enums\DomainStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreDomainRequest;
use App\Http\Resources\Platform\DomainResource;
use App\Models\Domain;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DomainController extends Controller
{
    public function index(Tenant $tenant)
    {
        return DomainResource::collection($tenant->domains()->latest()->get());
    }

    public function store(
        StoreDomainRequest $request,
        Tenant $tenant
    ): JsonResponse {

        $data = $request->validated();

        $domain = DB::transaction(function () use ($tenant, $data) {
            $isPrimary = (bool) ($data['is_primary'] ?? false);

            if ($isPrimary) {
                $tenant->domains()->update(['is_primary' => false]);
            }

            return $tenant->domains()->create([
                'domain' => $data['domain'],
                'status' => DomainStatus::PENDING,
                'is_primary' => $isPrimary,
            ]);
        });

        return response()->json([
            'message' => 'Domain added successfully.',
            'data' => DomainResource::make($domain),
        ], 201);
    }

    public function destroy(Tenant $tenant, Domain $domain)
    {
        abort_unless($domain->tenant_id === $tenant->id, 404);

        $domain->delete();

        return response()->json([
            'message' => 'Domain removed successfully.',
        ]);
    }
}
