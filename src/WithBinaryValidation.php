<?php

declare(strict_types=1);

namespace Jasara\Uuid;

trait WithBinaryValidation
{
    private static function validateBinary(string $binary): void
    {
        $shorts = unpack('n*', $binary);    // unpack into 8 x 16bit unsigned integers

        if (
            count($shorts) !== 8            // length
            || $shorts[4] >> 12 !== 8       // version
            || ($shorts[4] & 0xfff) > 0x7ff // max type
            || $shorts[5] >> 14 !== 2       // variant
        ) {
            throw JasaraUuidException::invalidUuid();
        }
    }
}
