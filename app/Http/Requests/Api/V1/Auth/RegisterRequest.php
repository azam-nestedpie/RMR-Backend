<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\V1Request;
use App\Models\Role;
use Illuminate\Validation\Rule;

class RegisterRequest extends V1Request
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
            'first_name' => ['string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in([1, 2, 3, 4, '1', '2', '3', '4', Role::RATER, Role::REPRESENTATIVE, Role::MANAGER_OF_RATERS, Role::MANAGER_OF_REPRESENTATIVES])],
            'fcm_token' => ['nullable', 'string'],
            'prefered_locale' => ['nullable', 'string', 'in:en,es,pt'],
            'bio' => ['nullable', 'string'],
            'company_name' => ['nullable', 'string'],
            'position' => ['nullable', 'string'],

            'industry' => ['nullable', 'integer', 'exists:industries,id'],

            'address' => 'nullable|array',
            'address.country' => 'nullable|string|max:100',
            'address.state' => 'nullable|string|max:100',
            'address.city' => 'nullable|string|max:100',
            'address.postal_code' => 'nullable|string|max:20',
            'address.address_line_1' => 'nullable|string|max:255',

        ];
    }
}
