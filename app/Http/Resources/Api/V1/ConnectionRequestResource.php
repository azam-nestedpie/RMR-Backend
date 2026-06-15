<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConnectionRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'firebase_uuid' => $this->firebase_uuid,
            'status' => $this->relationLoaded('status') ? $this->status?->name : null,
            'requester' => $this->relationLoaded('requester') ? UserResource::make($this->requester) : null,
            'target' => $this->relationLoaded('target') ? UserResource::make($this->target) : null,
            'manager' => $this->relationLoaded('manager') && $this->manager ? UserResource::make($this->manager) : null,
            'behalf_user' => $this->relationLoaded('behalfUser') && $this->behalfUser ? UserResource::make($this->behalfUser) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
