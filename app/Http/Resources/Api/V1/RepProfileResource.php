<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RepProfileResource extends JsonResource
{
    public function __construct(
        mixed $resource,
        private readonly ?float $averageRating,
        private readonly string $connectionStatus,
        private readonly ?LengthAwarePaginator $ratings,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'firebase_uuid' => $this->firebase_uid,
            'full_name' => trim(($this->first_name ?? '').' '.($this->last_name ?? '')),
            'company_name' => $this->company_name,
            'position' => $this->position,
            'average_rating' => $this->averageRating,
            'bio' => $this->bio,
            'email' => $this->email,
            'connection_status' => $this->connectionStatus,
            'ratings' => $this->ratings ? [
                'data' => RepRatingResource::collection($this->ratings->items()),
                'current_page' => $this->ratings->currentPage(),
                'last_page' => $this->ratings->lastPage(),
                'per_page' => $this->ratings->perPage(),
                'total' => $this->ratings->total(),
            ] : [
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 10,
                'total' => 0,
            ],
        ];
    }
}
