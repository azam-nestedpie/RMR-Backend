<?php

namespace App\Http\Requests\Api\V1\Migration;

use App\Http\Requests\Api\V1\V1Request;

abstract class MigrationRequest extends V1Request
{
    protected array $collections = [];

    public function collectionDocuments(string $collection): array
    {
        $validated = $this->validated();
        $nested = data_get($validated, "documents.$collection");

        if (is_array($nested)) {
            return $nested;
        }

        $documents = data_get($validated, 'documents', []);

        return is_array($documents) ? $documents : [];
    }

    protected function normalizeDocumentsPayload(): void
    {
        if ($this->has('documents')) {
            return;
        }

        $documents = [];

        foreach ($this->collections as $collection) {
            if ($this->has($collection)) {
                $documents[$collection] = $this->input($collection, []);
            }
        }

        if (! empty($documents)) {
            $this->merge(['documents' => $documents]);
        }
    }
}
