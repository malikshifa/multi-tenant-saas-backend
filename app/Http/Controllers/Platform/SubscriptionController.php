<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\SubscriptionRequest;
use App\Http\Resources\Platform\SubscriptionResource;
use App\Models\Subscription;
use App\Services\SubscriptionService;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    public function index()
    {
        return SubscriptionResource::collection(
            Subscription::with(['tenant', 'plan'])->latest()->paginate(20)
        );
    }

    public function store(SubscriptionRequest $request)
    {
        $subscription = $this->subscriptionService->create(
            $request->validated()
        );

        return response()->json([
            'message' => 'Subscription created successfully.',
            'data' => SubscriptionResource::make($subscription->load(['tenant', 'plan'])),
        ], 201);
    }

    public function show(Subscription $subscription)
    {
        return response()->json([
            'data' => SubscriptionResource::make($subscription->load(['tenant', 'plan'])),
        ]);
    }

    public function update(
        SubscriptionRequest $request,
        Subscription $subscription
    ) {
        $subscription = $this->subscriptionService->update(
            $subscription,
            $request->validated()
        );

        return response()->json([
            'message' => 'Subscription updated successfully.',
            'data' => SubscriptionResource::make($subscription->load(['tenant', 'plan'])),
        ]);
    }

    public function destroy(Subscription $subscription)
    {
        $subscription = $this->subscriptionService->cancel(
            $subscription
        );

        return response()->json([
            'message' => 'Subscription cancelled successfully.',
            'data' => SubscriptionResource::make($subscription),
        ]);
    }
}
