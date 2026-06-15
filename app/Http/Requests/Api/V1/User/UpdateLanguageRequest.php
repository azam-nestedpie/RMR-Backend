<?php

namespace App\Http\Requests\Api\V1\User;

use App\Http\Requests\Api\V1\V1Request;
use Illuminate\Validation\Rule;

class UpdateLanguageRequest extends V1Request
{
    public function authorize(): bool
    {
        return $this->user()?->can('profile.update.self') === true;
    }

    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::in(['en', 'es', 'pt'])],
        ];
    }
}
