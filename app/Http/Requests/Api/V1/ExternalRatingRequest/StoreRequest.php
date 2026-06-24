<?php

namespace App\Http\Requests\Api\V1\ExternalRatingRequest;

use App\Http\Requests\Api\V1\V1Request;
use App\Models\Role;

class StoreRequest extends V1Request
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::REPRESENTATIVE) === true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge([
                'email' => mb_strtolower(trim($email)),
            ]);
        }
    }
}
