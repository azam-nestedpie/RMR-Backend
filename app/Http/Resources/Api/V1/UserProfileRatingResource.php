<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileRatingResource extends JsonResource
{
    public function __construct(
        mixed $resource,
        private readonly bool $isFromCurrentUser = false,
        private readonly ?User $viewerRep = null,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        if ($this->viewerRep?->roles?->first()?->id === Role::REPRESENTATIVE) {
            return [
                'rating_id' => $this->firebase_uuid,
                'avg_rating' => $this->average_score,
                'rated_at' => $this->rated_at?->toIso8601String(),
                'firebase_uid' => $this->viewerRep->firebase_uid,
                'image_url' => $this->viewerRep->image_url,
                'full_name' => trim(($this->viewerRep->first_name ?? '').' '.($this->viewerRep->last_name ?? '')),
                'company_name' => $this->viewerRep->company_name,
                'position' => $this->viewerRep->position,
                'is_from_me' => $this->isFromCurrentUser,
            ];
        }

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
            'firebase_uid' => $rater?->firebase_uid,
            'position' => $rater?->position,
            'is_from_me' => $this->isFromCurrentUser,
        ];
    }
}
