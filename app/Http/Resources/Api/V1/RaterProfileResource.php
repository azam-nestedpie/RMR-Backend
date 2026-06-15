<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RaterProfileResource extends JsonResource
{
    public function __construct(
        mixed $resource,
        private readonly LengthAwarePaginator $ratings,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $ratingsCollection = collect($this->ratings->items())->map(
            fn ($rating) => new RaterRatingResource($rating, true)
        );

        return [
            'firebase_uuid' => $this->firebase_uid,
            'full_name' => trim(($this->first_name ?? '').' '.($this->last_name ?? '')),
            'company_name' => $this->company_name,
            'position' => $this->position,
            'bio' => $this->bio,
            'email' => $this->email,
            'connection_status' => $this->connectionStatus,
            'image_url' => $this->image_url,
            'ratings' => [
                'data' => $ratingsCollection,
                'current_page' => $this->ratings->currentPage(),
                'last_page' => $this->ratings->lastPage(),
                'per_page' => $this->ratings->perPage(),
                'total' => $this->ratings->total(),
            ],
        ];
    }
}
