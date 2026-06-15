<?php

namespace App\Http\Requests\Api\V1\Migration;

class ConnectionsRequest extends MigrationRequest
{
    protected array $collections = ['connections'];

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
