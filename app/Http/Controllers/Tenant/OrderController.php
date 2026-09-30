<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreOrderRequest;
use App\Http\Requests\Tenant\UpdateOrderStatusRequest;
use App\Http\Resources\Tenant\OrderResource;
use App\Models\Tenant\Order;
use App\Services\Tenant\OrderService;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $service
    ) {}

    public function index()
    {
        return OrderResource::collection(
            Order::with(['customer', 'items.product'])->latest()->paginate(20)
        );
    }

    public function store(StoreOrderRequest $request)
    {
        $order = $this->service->create($request->validated());

        return response()->json([
            'message' => 'Order created successfully.',
            'data' => OrderResource::make($order),
        ], 201);
    }

    public function show(Order $order)
    {
        return response()->json([
            'data' => OrderResource::make($order->load(['customer', 'items.product'])),
        ]);
    }

    public function update(UpdateOrderStatusRequest $request, Order $order)
    {
        $order = $this->service->updateStatus($order, $request->enum('status', OrderStatus::class));

        return response()->json([
            'message' => 'Order updated successfully.',
            'data' => OrderResource::make($order),
        ]);
    }

    public function cancel(Order $order)
    {
        $order = $this->service->cancel($order);

        return response()->json([
            'message' => 'Order cancelled successfully.',
            'data' => OrderResource::make($order),
        ]);
    }
}
