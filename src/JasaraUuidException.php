<?php

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

    public static function outOfBoundType(): static
    {
        return new static('type must be between 0 and 4095 inclusive.');
    }
}
