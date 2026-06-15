<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RatingItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'score' => $this->score,
            'question' => $this->relationLoaded('question') ? RatingQuestionResource::make($this->question) : null,
        ];
    }
}
