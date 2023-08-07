<?php

declare(strict_types=1);

namespace Jasara\Uuid;

trait ValidatesBinary
{
    private static function validateBinary(string $binary): void
    {
        $bytes = unpack('C*', $binary);

        if (
            count($bytes) !== 16 && // 128 bits
            $bytes[7] >> 4 !== 8 && // version
            $bytes[9] >> 6 !== 2 && // variant
            $bytes[13] >> 6 !== 0   // reserved
        ) {
            throw JasaraUuidException::invalidUuid();
        }
    }
}
