<?php

namespace App\Http\Requests\Api\V1\Connection;

use App\Http\Requests\Api\V1\V1Request;

class SendOnBehalfRequest extends V1Request
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'behalf_uid' => ['required', 'string', 'different:target_uid', 'exists:users,firebase_uid'],
            'target_uid' => ['required', 'string', 'exists:users,firebase_uid'],
        ];
    }
}
