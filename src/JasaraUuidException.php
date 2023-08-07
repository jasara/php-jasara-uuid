<?php

declare(strict_types=1);

namespace Jasara\Uuid;

use Exception;
use Throwable;

final class JasaraUuidException extends Exception
{
    private function __construct(
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function invalidUuid(): static
    {
        return new static('Invalid Uuid.');
    }

    public static function emptyMap(): static
    {
        return new static('Map cannot be empty.');
    }

    public static function outOfBoundType(): static
    {
        return new static('type must be between 0 and 4095 inclusive.');
    }

    public static function undefinedPrefix(string $prefix): static
    {
        return new static("Prefix '$prefix' does not have a corresponding type value.");
    }

    public static function undefinedType(int $type): static
    {
        return new static("Type '$type' does not have a corresponding prefix value.");
    }

    public static function invalidString(string $string): static
    {
        return new static("Given string value '$string' is invalid.");
    }

    public static function invalidPrefixed(string $prefixed): static
    {
        return new static("Given prefixed value '$prefixed' is invalid.");
    }
}
