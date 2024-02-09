<?php

declare(strict_types=1);

namespace Jasara\Uuid;

trait WithStaticMap
{
    private static array $map;
    private static array $map_inversed;

    public static function useMap(array $map): void
    {
        static::$map = [];
        static::$map_inversed = [];

        foreach ($map as $key => $value) {
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

    public static function getPrefix(int $type): string
    {
        if (!array_key_exists($type, static::$map)) {
            throw JasaraUuidException::undefinedType($type);
        }

        return static::$map[$type];
    }

    public static function getType(string $prefix): int
    {
        if (!array_key_exists($prefix, static::$map_inversed)) {
            throw JasaraUuidException::undefinedPrefix($prefix);
        }

        return static::$map_inversed[$prefix];
    }
}
