<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RatingQuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->user()?->prefered_locale ?? 'en';

        return [
            'id' => $this->id,
            'question_code' => $this->question_code,
            'title' => $this->{"title_{$locale}"},
            'display_order' => $this->whenPivotLoaded('industry_rating_questions', fn () => $this->pivot->display_order),
            'is_required' => $this->whenPivotLoaded('industry_rating_questions', fn () => (bool) $this->pivot->is_required),
        ];
    }
}
