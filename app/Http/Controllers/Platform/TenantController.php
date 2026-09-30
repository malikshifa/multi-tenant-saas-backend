<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreTenantRequest;
use App\Http\Requests\Platform\UpdateTenantRequest;
use App\Http\Resources\Platform\TenantResource;
use App\Models\Tenant;
use App\Services\TenantProvisioningService;
use App\Services\TenantService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class TenantController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected TenantService $service,
        protected TenantProvisioningService $provisioning
    ) {}

    public function index()
    {
        $this->authorize('viewAny', Tenant::class);

        return TenantResource::collection(
            Tenant::with('primaryDomain')->latest()->paginate(20)
        );
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        $this->authorize('create', Tenant::class);

        $tenant = $this->service->create(
            $request->validated()
        );

        return response()->json([
            'message' => 'Tenant provisioning started.',
            'data' => TenantResource::make($tenant),
        ], 202);
    }

    public function show(Tenant $tenant)
    {
        $this->authorize('view', $tenant);

        return response()->json([
            'data' => TenantResource::make($tenant->load('domains')),
        ]);
    }

    public function update(
        UpdateTenantRequest $request,
        Tenant $tenant
    ) {
        $this->authorize('update', $tenant);

        $tenant->update($request->validated());

        return response()->json([
            'message' => 'Tenant updated successfully.',
            'data' => TenantResource::make($tenant->fresh()),
        ]);
    }

    public function destroy(Tenant $tenant)
    {
        $this->authorize('delete', $tenant);

        $this->provisioning->destroy($tenant);

        $tenant->delete();

        return response()->json([
            'message' => 'Tenant deleted successfully.',
        ]);
    }

    public function suspend(Tenant $tenant)
    {
        $this->authorize('suspend', $tenant);

        return response()->json([
            'data' => TenantResource::make($this->service->suspend($tenant)),
        ]);
    }

    public function activate(Tenant $tenant)
    {
        $this->authorize('activate', $tenant);

        return response()->json([
            'data' => TenantResource::make($this->service->activate($tenant)),
        ]);
    }
}
