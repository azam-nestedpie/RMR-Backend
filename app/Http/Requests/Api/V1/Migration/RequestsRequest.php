<?php

namespace App\Http\Requests\Api\V1\Migration;

class RequestsRequest extends MigrationRequest
{
    protected array $collections = ['requests'];

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
