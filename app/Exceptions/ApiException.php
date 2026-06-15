<?php

namespace App\Exceptions;

use RuntimeException;

class ApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 400,
    ) {
        parent::__construct($message);
    }

    public static function badRequest(string $message): self
    {
        return new self($message);
    }

    public static function forbidden(string $message): self
    {
        return new self($message, 403);
    }

    public static function notFound(string $message): self
    {
        return new self($message, 404);
    }

    public static function conflict(string $message): self
    {
        return new self($message, 409);
    }

    public static function gone(string $message): self
    {
        return new self($message, 410);
    }
}
