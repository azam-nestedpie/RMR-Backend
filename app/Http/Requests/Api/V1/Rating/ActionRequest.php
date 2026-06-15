<?php

namespace App\Http\Requests\Api\V1\Rating;

use App\Http\Requests\Api\V1\V1Request;

class ActionRequest extends V1Request
{
    public function rules(): array
    {
        return [
            'request_uuid' => ['required', 'string'],
        ];
    }
}
