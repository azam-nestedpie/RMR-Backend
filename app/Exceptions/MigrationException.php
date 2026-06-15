<?php

namespace App\Exceptions;

use RuntimeException;

class MigrationException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $collection,
        private readonly string $docId,
        private readonly array $rawData = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function forDocument(
        string $collection, string $docId, string $reason,
        array $rawData = [], ?\Throwable $previous = null
    ): static {
        return new static(
            "Migration failed [{$collection}] doc [{$docId}]: {$reason}",
            $collection, $docId, $rawData, $previous
        );
    }

    public function getCollection(): string
    {
        return $this->collection;
    }

    public function getDocId(): string
    {
        return $this->docId;
    }

    public function getRawData(): array
    {
        return $this->rawData;
    }
}
