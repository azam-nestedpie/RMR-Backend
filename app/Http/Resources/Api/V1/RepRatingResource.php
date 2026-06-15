<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RepRatingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $rater = $this->relationLoaded('rater') ? $this->rater : null;

        return [
            'rating_id' => $this->firebase_uuid,
            'rating' => $this->average_score,
            'rated_at' => $this->rated_at?->toIso8601String(),
            'image_url' => $rater?->image_url,
            'full_name' => $rater ? trim(($rater->first_name ?? '').' '.($rater->last_name ?? '')) : null,
            'company_name' => $rater?->company_name,
            'position' => $rater?->position,
        ];
    }
}
