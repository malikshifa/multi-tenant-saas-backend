<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PlanRequest;
use App\Http\Resources\Platform\PlanResource;
use App\Models\Plan;

class PlanController extends Controller
{
    public function index()
    {
        return PlanResource::collection(Plan::latest()->paginate(20));
    }

    public function store(PlanRequest $request)
    {
        $plan = Plan::create(
            $request->validated()
        );

        return response()->json([
            'message' => 'Plan created successfully.',
            'data' => PlanResource::make($plan),
        ], 201);
    }

    public function show(Plan $plan)
    {
        return response()->json([
            'data' => PlanResource::make($plan),
        ]);
    }

    public function update(
        PlanRequest $request,
        Plan $plan
    ) {
        $plan->update(
            $request->validated()
        );

        return response()->json([
            'message' => 'Plan updated successfully.',
            'data' => PlanResource::make($plan->fresh()),
        ]);
    }

    public function destroy(Plan $plan)
    {
        $plan->delete();

        return response()->json([
            'message' => 'Plan deleted successfully.',
        ]);
    }
}
