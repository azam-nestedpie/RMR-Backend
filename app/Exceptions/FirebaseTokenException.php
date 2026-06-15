<?php

namespace App\Exceptions;

use RuntimeException;

class FirebaseTokenException extends RuntimeException
{
    public static function invalidToken(string $detail = ''): static
    {
        return new static('Firebase token is invalid or expired.'.($detail ? " {$detail}" : ''));
    }

    public static function serviceUnavailable(): static
    {
        return new static('Firebase Auth service is currently unavailable.');
    }
}
