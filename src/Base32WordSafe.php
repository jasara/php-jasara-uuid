<?php

declare(strict_types=1);

namespace Jasara\Uuid;

use ParagonIE\ConstantTime\Base32;

class Base32WordSafe extends Base32
{
    public const ALPHABET = '23456789CFGHJMPQRVWXcfghjmpqrvwx';

    protected static function encode5Bits(int $src): string
    {
        return static::ALPHABET[$src];
    }

    protected static function decode5Bits(int $src): int
    {
        return strpos(static::ALPHABET, pack('C', $src));
    }
}
