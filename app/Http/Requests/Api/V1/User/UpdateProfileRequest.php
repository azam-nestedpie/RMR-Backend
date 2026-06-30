<?php

namespace App\Http\Requests\Api\V1\User;

use App\Http\Requests\Api\V1\V1Request;

class UpdateProfileRequest extends V1Request
{
    public function authorize(): bool
    {
        return $this->user()?->can('profile.update.self') === true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'bio' => ['sometimes', 'nullable', 'string'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:1024'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'fcm_token' => ['sometimes', 'nullable', 'string'],
            'address' => ['sometimes', 'nullable', 'array'],
            'address.country' => ['nullable', 'string', 'max:100'],
            'address.state' => ['nullable', 'string', 'max:100'],
            'address.city' => ['nullable', 'string', 'max:100'],
            'address.postal_code' => ['nullable', 'string', 'max:20'],
            'address.address_line_1' => ['nullable', 'string', 'max:255'],
            'industry' => ['sometimes', 'nullable', 'integer', 'exists:industries,id'],
        ];
    }
}
