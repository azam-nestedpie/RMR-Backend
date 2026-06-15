<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConnectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'firebase_uuid' => $this->firebase_uuid,
            'sender' => $this->relationLoaded('userA') ? UserResource::make($this->userA) : null,
            'receiver' => $this->relationLoaded('userB') ? UserResource::make($this->userB) : null,
            'connected_by_uid' => $this->connected_by_uid,
            'source_request_id' => $this->source_request_id,
            'connected_at' => $this->connected_at,
            'disconnected_at' => $this->disconnected_at,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
