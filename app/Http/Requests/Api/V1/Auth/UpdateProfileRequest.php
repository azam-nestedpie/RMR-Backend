<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\V1Request;

class UpdateProfileRequest extends V1Request
{
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'bio' => ['nullable', 'string'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'fcm_token' => ['nullable', 'string'],
        ];
    }
}
