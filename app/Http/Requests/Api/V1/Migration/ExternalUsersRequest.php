<?php

namespace App\Http\Requests\Api\V1\Migration;

class ExternalUsersRequest extends MigrationRequest
{
    protected array $collections = ['external_users'];

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
