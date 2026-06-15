<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RaterRatingResource extends JsonResource
{
    public function __construct(
        mixed $resource,
        private readonly bool $isForMe,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $rater = $this->relationLoaded('rater') ? $this->rater : null;
        $raterName = $rater ? trim(($rater->first_name ?? '').' '.($rater->last_name ?? '')) : null;

        $fullName = $raterName && $this->isForMe
            ? $raterName.' (Me)'
            : $raterName;

        return [
            'rating_id' => $this->firebase_uuid,
            'rating' => $this->average_score,
            'rated_at' => $this->rated_at?->toIso8601String(),
            'image_url' => $rater?->image_url,
            'full_name' => $fullName,
        ];
    }
}
