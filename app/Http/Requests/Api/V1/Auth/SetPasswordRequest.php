<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\V1Request;

class SetPasswordRequest extends V1Request
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
