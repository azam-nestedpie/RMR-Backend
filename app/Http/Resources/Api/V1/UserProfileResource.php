<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Role;
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
        private readonly bool $isFavourite = false,
        private readonly ?int $ratingCount = null,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $targetRole = $this->roles->first();

        $ratingData = null;

        if ($this->ratings) {
            $ratingData = [
                'data' => collect($this->ratings->items())->map(
                    fn ($rating) => new UserProfileRatingResource(
                        $rating,
                        $this->currentUserUid && ($rating->rater_firebase_uid === $this->currentUserUid || $rating->rep_firebase_uid === $this->currentUserUid),
                    )
                ),
                'current_page' => $this->ratings->currentPage(),
                'last_page' => $this->ratings->lastPage(),
                'per_page' => $this->ratings->perPage(),
                'total' => $this->ratings->total(),
            ];
        }

        $response = [
            'firebase_uid' => $this->firebase_uid,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => trim(($this->first_name ?? '').' '.($this->last_name ?? '')),
            'company_name' => $this->company_name,
            'position' => $this->position,
            'bio' => $this->bio,
            'email' => $this->email,
            'image_url' => $this->image_url,
            'role' => $targetRole ? [
                'id' => $targetRole->id,
                'name' => $targetRole->name,
                'description' => $targetRole->description,
            ] : null,
            'connection_status' => $this->connectionStatus,
            'is_favourite' => (int) $this->isFavourite,
            'ratings' => $ratingData ?? [
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 10,
                'total' => 0,
            ],
        ];

        if ($targetRole && $targetRole->id === Role::REPRESENTATIVE) {
            $response['avg_rating'] = $this->averageRating;
            $response['rating_count'] = $this->ratingCount ?? 0;
        }

        return $response;
    }
}
