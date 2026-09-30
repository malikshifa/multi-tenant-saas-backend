<?php

namespace App\Http\Resources\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DomainResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'domain' => $this->domain,
            'status' => $this->status,
            'is_primary' => $this->is_primary,
            'verified_at' => $this->verified_at,
            'created_at' => $this->created_at,
        ];
    }
}
