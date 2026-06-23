<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompletedRatingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $counterparty = $this->relationLoaded('counterparty') ? $this->counterparty : null;

        return [
            'firebase_uid' => $counterparty?->firebase_uid,
            'full_name' => $counterparty
                ? trim(($counterparty->first_name ?? '').' '.($counterparty->last_name ?? ''))
                : null,
            'image_url' => $counterparty?->image_url,
            'rating' => $this->average_score,
        ];
    }
}
