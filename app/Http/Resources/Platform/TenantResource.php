<?php

namespace App\Http\Resources\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status,
            'provisioned_at' => $this->provisioned_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'primary_domain' => DomainResource::make($this->whenLoaded('primaryDomain')),
            'domains' => DomainResource::collection($this->whenLoaded('domains')),
        ];
    }
}
