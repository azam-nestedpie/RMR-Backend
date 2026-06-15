<?php

namespace App\Http\Requests\Api\V1\Rating;

use App\Http\Requests\Api\V1\V1Request;

class ExternalStoreRequest extends V1Request
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
            'rep_uid' => ['required', 'string', 'exists:users,firebase_uid'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'unique:users,email', 'email', 'max:191'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'question_id' => ['required', 'integer', 'exists:rating_questions,id'],
            'score' => ['required', 'numeric', 'min:1', 'max:5'],
        ];
    }
}
