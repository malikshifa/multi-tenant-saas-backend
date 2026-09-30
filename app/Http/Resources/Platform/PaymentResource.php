<?php

namespace App\Http\Resources\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'subscription_id' => $this->subscription_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'gateway_reference' => $this->gateway_reference,
            'checkout_url' => $this->checkout_url,
            'transaction_id' => $this->transaction_id,
            'paid_at' => $this->paid_at,
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at,
            'tenant' => TenantResource::make($this->whenLoaded('tenant')),
            'subscription' => SubscriptionResource::make($this->whenLoaded('subscription')),
        ];
    }
}
