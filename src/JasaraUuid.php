<?php

declare(strict_types=1);

namespace Jasara\Uuid;

use DateTimeInterface;
use ParagonIE\ConstantTime\Base32Hex;
use Ramsey\Uuid\Uuid;
use Stringable;

final class JasaraUuid implements Stringable
{
    use StaticMap;
    use ValidatesBinary;

    private const STANDARD_PATTERN = '/^([0-9a-f]{8})-([0-9a-f]{4})-([0-9a-f]{4})-([0-9a-f]{4})-([0-9a-f]{12})$/';
    private const PREFIXED_PATTERN = '/^([a-z])+_([0-9a-v]{22})$/';

    private function __construct(
        private readonly string $binary,
        ?bool $should_validate = true,
    ) {
        if ($should_validate) {
            static::validateBinary($binary);
        }
    }

    public static function from(string $uuid): static
    {
        $length = strlen($uuid);

        if ($length === 36 && preg_match(static::STANDARD_PATTERN, $uuid, $matches)) {
            return new static(hex2bin(implode(array_slice($matches, 1))));
        }

        if ($length > 23 && preg_match(static::PREFIXED_PATTERN, $uuid)) {
            return static::fromPrefixed($uuid);
        }

        if ($length === 16) {
            return new static($uuid);
        }

        throw JasaraUuidException::invalidUuid();
    }

    public static function generate(
        int|string|JasaraUuidType $type,
        ?DateTimeInterface $datetime = null,
    ): static {
        if ($type instanceof JasaraUuidType) {
            $type = $type->numeric();
        }

        if (is_string($type)) {
            $type = static::getType($type);
        }

        if ($type < 0 || $type > 0xfff) {
            throw JasaraUuidException::outOfBoundType();
        }

        // unpack uuid7
        $shorts = unpack('n*', Uuid::uuid7($datetime)->getBytes());

        // set type and change version to 8
        $shorts[4] = $type | 0x8000;

        // set variant
        $shorts[5] = $shorts[5] & 0x3fff | 0x8000;

        // reset reserved bits
        $shorts[7] = $shorts[7] & 0x3fff;

        return new static(pack('n*', ...$shorts), false);
    }

    // public static function generate(
    //     int|JasaraUuidType $type,
    //     ?DateTimeInterface $datetime = null,
    // ): static {
    //     if ($type instanceof JasaraUuidType) {
    //         $type = $type->numeric();
    //     }

    //     if ($type < 0 || $type > 0xfff) {
    //         throw JasaraUuidException::outOfBoundType();
    //     }

    //     $epoch_ms = $datetime
    //             ? $datetime->format('Uv')
    //             : (new \DateTime())->format('Uv');

    //     $ts =  PHP_INT_SIZE >= 8
    //         ? substr(pack('J', (int) $epoch_ms), -6)
    //         : str_pad(BigInteger::of($epoch_ms)->toBytes(false), 6, "\x00", STR_PAD_LEFT);

    //     list(1 => $rnd_hi, 2 => $rnd_low) = unpack('N2', random_bytes(8));

    //     $type |= 0x8000;
    //     $rnd_hi = $rnd_hi & 0x3fffffff | 0x80000000;
    //     $rnd_low &= 0x3fffffff;

    //     return new static($ts . pack('nN2', $type, $rnd_hi, $rnd_low), false);
    // }

    public function toStandard(): string
    {
        $hex = bin2hex($this->binary);

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

    public function __toString(): string
    {
        return $this->toStandard();
    }

    public function toPrefixed(): string
    {
        $shorts = unpack('n*', $this->binary);

        $prefix = static::getPrefix($shorts[4] & 0xfff);

        $shorts = [
            ...array_slice($shorts, 4, 4),
            ...array_slice($shorts, 0, 3),
        ];

        // shift left 2
        for($i = 0; $i < 7; $i++) {
            $carry = ($i < 6 ? $shorts[$i + 1] : 0) >> 14;
            $shorts[$i] = ($shorts[$i] & 0x3fff) << 2 | $carry;
        }

        return $prefix . '_' . substr(Base32Hex::encode(pack('n*', ...$shorts)), 0, 22);
    }

    private static function fromPrefixed(string $prefixed): static
    {
        list($prefix, $rest) = explode('_', $prefixed);

        $type = static::getType($prefix);

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
