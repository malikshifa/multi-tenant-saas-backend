<?php

namespace App\Http\Resources\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'issued_at' => $this->issued_at,
            'tenant' => TenantResource::make($this->whenLoaded('tenant')),
            'subscription' => SubscriptionResource::make($this->whenLoaded('subscription')),
        ];
    }
}
