<?php

namespace App\Http\Requests\Api\V1\Connection;

use App\Http\Requests\Api\V1\V1Request;

class SendBulkRequest extends V1Request
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'target_uids' => ['required', 'array', 'min:1'],
            'target_uids.*' => ['required', 'string', 'distinct', 'exists:users,firebase_uid'],
        ];
    }
}
