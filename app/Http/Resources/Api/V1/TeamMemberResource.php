<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->firebase_uid,
            'name' => trim(($this->first_name ?? '').' '.($this->last_name ?? '')),
            'image_url' => $this->image_url,
            'rating' => $this->salesRepProfile?->avg_rating,
            'ratings_count' => $this->salesRepProfile?->ratings_count,
            'status' => 'Active',
        ];
    }
}
