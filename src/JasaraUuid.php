<?php

declare(strict_types=1);

namespace Jasara\Uuid;

use Brick\Math\BigInteger;
use DateTimeInterface;
use ParagonIE\ConstantTime\Base32Hex;
use Stringable;

final class JasaraUuid implements Stringable
{
    private static array $map;
    private static array $map_inversed;

    private function __construct(
        private readonly string $bytes,
    ) {
    }

    public static function useMap(array $map): void
    {
        static::$map = [];
        static::$map_inversed = [];

        foreach($map as $key => $value) {
            if (is_string($value)) {
                static::$map[$key] = $value;
                static::$map_inversed[$value] = $key;
                continue;
            }

            if ($value instanceof JasaraUuidType) {
                static::$map[$value->numeric()] = $value->prefix();
                static::$map_inversed[$value->prefix()] = $value->numeric();
                continue;
            }
        }
    }

    public static function getMap(): array
    {
        return static::$map;
    }

    public static function generate(int|JasaraUuidType $type, ?DateTimeInterface $datetime = null): static
    {
        if ($type instanceof JasaraUuidType) {
            $type = $type->numeric();
        }

        if ($type < 0 || $type > 0xfff) {
            throw JasaraUuidException::outOfBoundType();
        }

        $epoch_ms = $datetime
                ? $datetime->format('Uv')
                : (new \DateTime())->format('Uv');

        $ts =  PHP_INT_SIZE >= 8
            ? substr(pack('J', (int) $epoch_ms), -6)
            : str_pad(BigInteger::of($epoch_ms)->toBytes(false), 6, "\x00", STR_PAD_LEFT);

        list(
            1 => $rnd_hi,
            2 => $rnd_low,
        ) = unpack('N2', random_bytes(8));

        return new static($ts . pack('nN2', $type | 0x8000, $rnd_hi & 0x3fffffff | 0x80000000, $rnd_low & 0x3fffffff));
    }

    public function __toString(): string
    {
        $hex = bin2hex($this->bytes);

        return substr($hex, 0, 8)
            . '-'
            . substr($hex, 8, 4)
            . '-'
            . substr($hex, 12, 4)
            . '-'
            . substr($hex, 16, 4)
            . '-'
            . substr($hex, 20, 12)
        ;
    }

    public function prefixed(): string
    {
        $shorts = unpack('n*', $this->bytes);

        $type = $shorts[4] & 0x3fff;

        if (! array_key_exists($type, static::$map)) {
            throw JasaraUuidException::undefinedType($type);
        }

        $prefix = static::$map[$type];

        $shorts = [
            ...array_slice($shorts, 4, 4),
            ...array_slice($shorts, 0, 3),
        ];

        // shift left 2
        for($i = 0; $i < 7; $i++) {
            $carry = ($i < 6 ? $shorts[$i+1] : 0) >> 14;
            $shorts[$i] = ($shorts[$i] & 0x3fff) << 2 | $carry;
        }

        return $prefix . '_' . substr(Base32Hex::encode(pack('n*', ...$shorts)), 0, 22);
    }

    public static function fromPrefixed(string $prefixed): static
    {
        if (1 !== preg_match('/^[a-z]+_[0-9a-v]{22}$/', $prefixed)) {
            throw JasaraUuidException::invalidPrefixed($prefixed);
        }

        list($prefix, $rest) = explode('_', $prefixed);

        if (! array_key_exists($prefix, static::$map_inversed)) {
            throw JasaraUuidException::undefinedPrefix($prefix);
        }

        $type = static::$map_inversed[$prefix];

        $shorts = unpack('n*', Base32Hex::decode($rest));

        // shift right 2
        for($i = 1, $carry = 0; $i <= 7; $i++) {
            $current = $shorts[$i];
            $shorts[$i] = ($current >> 2) | ($carry << 14);
            $carry = $current & 0x0003;
        }

        $ordered = [
            $shorts[5], // ts
            $shorts[6], // ts
            $shorts[7], // ts
            $type | 0x8000,
            $shorts[1] | 0x8000, // rn
            $shorts[2], // rn
            $shorts[3], // rn
            $shorts[4], // rn
        ];

        return new static(pack('n*', ...$ordered));
    }
}
