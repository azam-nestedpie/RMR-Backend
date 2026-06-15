<?php

namespace App\Http\Requests\Api\V1\Migration;

class UsersRequest extends MigrationRequest
{
    protected array $collections = ['users'];

    protected function prepareForValidation(): void
    {
        $this->normalizeDocumentsPayload();
    }

    public function rules(): array
    {
        return [
            'documents' => ['required', 'array'],
            'documents.*' => ['array'],
        ];
    }
}
