<?php

namespace App\Http\Requests\Api\V1\Connection;

use App\Http\Requests\Api\V1\V1Request;

class SendRequest extends V1Request
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('target_uids') && ! is_array($this->input('target_uids'))) {
            $this->merge([
                'target_uids' => [$this->input('target_uids')],
            ]);

            return;
        }

        if ($this->filled('target_uid')) {
            $this->merge([
                'target_uids' => [$this->input('target_uid')],
            ]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'target_uid' => ['nullable', 'string', 'exists:users,firebase_uid'],
            'target_uids' => ['required', 'array', 'min:1'],
            'target_uids.*' => ['required', 'string', 'distinct', 'exists:users,firebase_uid'],
        ];
    }
}
