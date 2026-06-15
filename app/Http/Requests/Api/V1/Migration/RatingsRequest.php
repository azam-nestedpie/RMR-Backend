<?php

namespace App\Http\Requests\Api\V1\Migration;

class RatingsRequest extends MigrationRequest
{
    protected array $collections = ['ratings'];

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
