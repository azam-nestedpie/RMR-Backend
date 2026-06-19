<?php

namespace App\Http\Requests\Api\V1\User;

use App\Http\Requests\Api\V1\V1Request;
use Illuminate\Contracts\Validation\Validator as ValidationValidator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SearchRequest extends V1Request
{
    private array $allowed = [
        'first_name', 'last_name', 'email', 'company_name',
        'position', 'address', 'industry', 'role', 'q',
    ];

    private array $searchable = [
        'first_name', 'last_name', 'email', 'company_name',
        'position', 'address', 'industry', 'role',
    ];

    public function rules(): array
    {
        return [
            'q' => ['prohibited'],
            'first_name' => ['sometimes', 'string',  'max:100'],
            'last_name' => ['sometimes', 'string',  'max:100'],
            'email' => ['sometimes', 'string', 'email', 'max:191'],
            'company_name' => ['sometimes', 'string',  'max:255'],
            'position' => ['sometimes', 'string',  'max:255'],
            'address' => ['sometimes', 'string',  'max:255'],
            'industry' => ['sometimes', 'string', 'min:2', 'max:100'],
            'role' => ['nullable', 'string', Rule::in(['rater', 'rep', 'manager_of_raters', 'manager_of_reps'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $extra = array_diff(array_keys($this->all()), $this->allowed);

            if (! empty($extra)) {
                $validator->errors()->add('search', 'No User Found According To Your Search');
            }

            $hasAny = collect($this->searchable)->contains(fn ($field) => $this->filled($field));

            if (! $hasAny) {
                $validator->errors()->add('search', 'Kindly choose at least one field to search.');
            }
        });
    }

    protected function failedValidation(ValidationValidator $validator): never
    {
        $message = $validator->errors()->has('search')
            ? $validator->errors()->first('search')
            : 'Validation failed.';

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $validator->errors(),
        ], 422));
    }
}
