<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConnectableUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'firebase_uid' => $this->firebase_uid,
            'full_name' => trim(($this->first_name ?? '').' '.($this->last_name ?? '')),
            'image_url' => $this->image_url,
            'company_name' => $this->company_name,
            'position' => $this->position,
            'connection_uuid' => $this->when($this->connection_uuid !== null, $this->connection_uuid),
            'connection_status' => $this->connection_status ?? null,
        ];

        if ($this->relationLoaded('salesRepProfile') && $this->salesRepProfile) {
            $data['avg_rating'] = $this->salesRepProfile->avg_rating;
            $data['ratings_count'] = $this->salesRepProfile->ratings_count;
        }

        return $data;
    }
}
