<?php

namespace App\Http\Requests\Api\V1\Team;

use App\Http\Requests\Api\V1\V1Request;

class ActionRequest extends V1Request
{
    public function rules(): array
    {
        return [
            'request_uuid' => ['required', 'string', 'exists:team_requests,firebase_uuid'],
        ];
    }
}
