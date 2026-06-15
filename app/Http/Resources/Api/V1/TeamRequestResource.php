<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'firebase_uuid' => $this->firebase_uuid,
            'status' => $this->relationLoaded('status') ? $this->status?->name : null,
            'manager' => $this->relationLoaded('manager') ? ConnectableUserResource::make($this->manager) : null,
            'target' => $this->relationLoaded('target') ? ConnectableUserResource::make($this->target) : null,
            'manager_role' => $this->relationLoaded('managerRole') ? RoleResource::make($this->managerRole) : null,
            'responded_at' => $this->responded_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
