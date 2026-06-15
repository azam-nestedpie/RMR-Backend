<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesRepUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'avg_rating' => $this->avg_rating,
            'ratings_count' => $this->ratings_count,
            'is_subscribed' => $this->is_subscribed,
            'subscription_started_at' => $this->subscription_started_at,
            'subscription_expires_at' => $this->subscription_expires_at,
            'engagement_rate' => $this->engagement_rate,
            'resolution_rate' => $this->resolution_rate,
        ];
    }
}
