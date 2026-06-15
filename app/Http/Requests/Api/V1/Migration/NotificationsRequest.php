<?php

namespace App\Http\Requests\Api\V1\Migration;

class NotificationsRequest extends MigrationRequest
{
    protected array $collections = ['notifications'];

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
