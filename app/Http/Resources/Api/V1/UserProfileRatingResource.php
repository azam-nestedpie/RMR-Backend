<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileRatingResource extends JsonResource
{
    public function __construct(
        mixed $resource,
        private readonly bool $isFromCurrentUser = false,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $rater = $this->relationLoaded('rater') ? $this->rater : null;

        return [
            'rating_id' => $this->firebase_uuid,
            'avg_rating' => $this->average_score,
            'rated_at' => $this->rated_at?->toIso8601String(),
            'image_url' => $rater?->image_url,
            'full_name' => $rater
                ? trim(($rater->first_name ?? '').' '.($rater->last_name ?? ''))
                : null,
            'company_name' => $rater?->company_name,
            'position' => $rater?->position,
            'is_from_me' => $this->isFromCurrentUser,
        ];
    }
}
