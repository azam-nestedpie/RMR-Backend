<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompletedRatingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'firebase_uuid' => $this->firebase_uuid,
            'average_score' => $this->average_score,
            'rated_at' => $this->rated_at?->toIso8601String(),
            'rater' => $this->relationLoaded('rater') && $this->rater
                ? [
                    'firebase_uid' => $this->rater->firebase_uid,
                    'full_name' => trim(($this->rater->first_name ?? '').' '.($this->rater->last_name ?? '')),
                    'image_url' => $this->rater->image_url,
                ]
                : null,
            'rep' => $this->relationLoaded('rep') && $this->rep
                ? [
                    'firebase_uid' => $this->rep->firebase_uid,
                    'full_name' => trim(($this->rep->first_name ?? '').' '.($this->rep->last_name ?? '')),
                    'image_url' => $this->rep->image_url,
                ]
                : null,
        ];
    }
}
