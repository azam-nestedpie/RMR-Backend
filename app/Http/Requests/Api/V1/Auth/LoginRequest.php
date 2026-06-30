<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\V1Request;

class LoginRequest extends V1Request
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'fcm_token' => ['nullable', 'string'],
            'prefered_locale' => ['nullable', 'string', 'in:en,es,pt'],
        ];
    }
}
