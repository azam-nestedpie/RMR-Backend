<?php

namespace App\Http\Requests\Api\V1\Migration;

class RunAllRequest extends MigrationRequest
{
    protected array $collections = [
        'users',
        'external_users',
        'requests',
        'connections',
        'ratings',
        'notifications',
    ];

    protected function prepareForValidation(): void
    {
        $this->normalizeDocumentsPayload();
    }

    public function rules(): array
    {
        return [
            'documents' => ['required', 'array'],
            'documents.users' => ['nullable', 'array'],
            'documents.external_users' => ['nullable', 'array'],
            'documents.requests' => ['nullable', 'array'],
            'documents.connections' => ['nullable', 'array'],
            'documents.ratings' => ['nullable', 'array'],
            'documents.notifications' => ['nullable', 'array'],
        ];
    }
}
