<?php

namespace Jasara\Uuid;

use Brick\Math\BigInteger;
use DateTimeInterface;
use ParagonIE\ConstantTime\Base32Hex;
use Ramsey\Uuid\Uuid;

final class JasaraUuid
{
    private static array $map;

    private function __construct(
        private readonly string $bytes,
    ) {
    }

    public static function setMap(array $map): void
    {
        // TODO: validate
        static::$map = $map;
    }

    public static function getMap(): array
    {
        return static::$map;
    }

    private static function type2prefix(int $type): string
    {
        // TODO: throw exception if type is not set
        return static::$map[$type];
    }

    private static function prefix2Type(string $prefix): int
    {
        // TODO: throw exception if prefix is not set
        return array_search($prefix, static::getMap());
    }

    public function bytes(): string
    {
        return $this->bytes;
    }

    public static function generate(int $type, ?DateTimeInterface $datetime = null): static
    {
        // validate type (0 - 4095) i.e. 12 bits unsigned int.
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

    public static function fromJasaraString(string $string): static
    {
        list($prefix, $rest) = explode('_', $string);

        $type = static::prefix2Type($prefix);

        $shorts = unpack('n*', Base32Hex::decodeNoPadding($rest));

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

    public function standardString(): string
    {
        return Uuid::fromBytes($this->bytes);
    }

    public function jasaraString(): string
    {
        $shorts = unpack('n*', $this->bytes);

        $type = $shorts[4] & 0x3fff;

        $shorts = [
            ...array_slice($shorts, 4, 4),
            ...array_slice($shorts, 0, 3),
        ];

        // shift left 2
        for($i = 0; $i < 7; $i++) {
            $carry = ($i < 6 ? $shorts[$i+1] : 0) >> 14;
            $shorts[$i] = ($shorts[$i] & 0x3fff) << 2 | $carry;
        }

        return static::type2prefix($type) . '_' . substr(Base32Hex::encodeUnpadded(pack('n*', ...$shorts)), 0, 22);
    }
}
