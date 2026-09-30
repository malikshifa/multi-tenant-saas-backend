<?php

namespace App\Services\Tenant;

use App\Enums\OrderStatus;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderService
{
    public function create(array $data): Order
    {
        return DB::connection('tenant')->transaction(function () use ($data) {
            $order = Order::create([
                'customer_id' => $data['customer_id'],
                'status' => OrderStatus::PENDING,
                'notes' => $data['notes'] ?? null,
            ]);

            $total = 0;

            foreach ($data['items'] as $item) {
                // Lock the row so concurrent orders can't both oversell the same stock.
                $product = Product::where('is_active', true)
                    ->lockForUpdate()
                    ->findOrFail($item['product_id']);

                if ($product->stock_quantity < $item['quantity']) {
                    throw new RuntimeException(
                        "Insufficient stock for product '{$product->name}'."
                    );
                }

                $lineTotal = $product->price * $item['quantity'];

                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'line_total' => $lineTotal,
                ]);

                $product->decrement('stock_quantity', $item['quantity']);

                $total += $lineTotal;
            }

            $order->update(['total_amount' => $total]);

            return $order->fresh('items.product');
        });
    }

    public function updateStatus(Order $order, OrderStatus $status): Order
    {
        $order->update(['status' => $status]);

        return $order->fresh();
    }

    public function cancel(Order $order): Order
    {
        return DB::connection('tenant')->transaction(function () use ($order) {
            if ($order->status === OrderStatus::CANCELLED) {
                return $order;
            }

            foreach ($order->items as $item) {
                $item->product()->lockForUpdate()->increment('stock_quantity', $item->quantity);
            }

            $order->update(['status' => OrderStatus::CANCELLED]);

            return $order->fresh();
        });
    }
}
