<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileResource extends JsonResource
{
    public function __construct(
        mixed $resource,
        private readonly string $connectionStatus,
        private readonly ?LengthAwarePaginator $ratings,
        private readonly ?float $averageRating,
        private readonly ?string $currentUserUid = null,
        private readonly ?string $viewerRoleName = null,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $targetRole = $this->roles->first();
        $targetRoleName = $targetRole?->name;

        $ratingData = null;

        if ($this->ratings) {
            $ratingData = [
                'data' => collect($this->ratings->items())->map(
                    fn ($rating) => new UserProfileRatingResource(
                        $rating,
                        $this->currentUserUid && $rating->rater_firebase_uid === $this->currentUserUid,
                    )
                ),
                'current_page' => $this->ratings->currentPage(),
                'last_page' => $this->ratings->lastPage(),
                'per_page' => $this->ratings->perPage(),
                'total' => $this->ratings->total(),
            ];
        }

        return [
            'firebase_uuid' => $this->firebase_uid,
            'full_name' => trim(($this->first_name ?? '').' '.($this->last_name ?? '')),
            'company_name' => $this->company_name,
            'position' => $this->position,
            'bio' => $this->bio,
            'email' => $this->email,
            'image_url' => $this->image_url,
            'role' => $targetRoleName,
            'connection_status' => $this->connectionStatus,
            'average_rating' => $this->averageRating,
            'ratings' => $ratingData ?? [
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 10,
                'total' => 0,
            ],
        ];
    }
}
