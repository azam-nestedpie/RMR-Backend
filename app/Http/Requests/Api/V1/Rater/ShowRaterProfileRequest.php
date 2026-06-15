<?php

namespace App\Http\Requests\Api\V1\Rater;

use App\Http\Requests\Api\V1\V1Request;

class ShowRaterProfileRequest extends V1Request
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('rep') === true;
    }

    public function rules(): array
    {
        return [
            'raterUid' => ['prohibited'],
        ];
    }
}
