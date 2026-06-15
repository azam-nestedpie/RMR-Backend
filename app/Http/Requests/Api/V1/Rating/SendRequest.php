<?php

namespace App\Http\Requests\Api\V1\Rating;

use App\Http\Requests\Api\V1\V1Request;

class SendRequest extends V1Request
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_uid' => ['required', 'string', 'exists:users,firebase_uid'],
        ];
    }
}
