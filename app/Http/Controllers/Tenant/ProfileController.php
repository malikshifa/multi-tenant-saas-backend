<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        // return response()->json([
        //     'data' => 'text',
        // ]);
        $tenant = $request->attributes->get('tenant');

        return response()->json([
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
                'provisioned_at' => $tenant->provisioned_at,
                'primary_domain' => $tenant->primaryDomain?->domain,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $tenant = $request->attributes->get('tenant');

        $tenant->update([
            'name' => $validated['name'],
        ]);

        return response()->json([
            'message' => 'Tenant profile updated successfully.',
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
                'provisioned_at' => $tenant->provisioned_at,
                'primary_domain' => $tenant->primaryDomain?->domain,
            ],
        ]);
    }
}
