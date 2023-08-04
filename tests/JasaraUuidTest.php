<?php

use Jasara\Uuid\JasaraUuid;
use Jasara\Uuid\JasaraUuidException;
use Jasara\Uuid\JasaraUuidType;

describe('useMap', function () {

    test('sets map (int => string)', function () {
        JasaraUuid::useMap([
            0 => 'zero',
            1 => 'one',
        ]);

        expect(JasaraUuid::getMap())->toBe([
            0 => 'zero',
            1 => 'one',
        ]);
    });

    test('map need not start at index 0 (int => string)', function () {
        JasaraUuid::useMap([
            8 => 'prod',
            16 => 'cus',
        ]);

        expect(JasaraUuid::getMap())->toBe([
            8 => 'prod',
            16 => 'cus',
        ]);
    });

    test('map can be JasaraUuidType[]', function () {
        $generateType = fn (int $numeric, string $prefix) => new class ($numeric, $prefix) implements JasaraUuidType {
            public function __construct(
                private int $numeric,
                private string $prefix,
            ) {
            }

            public function prefix(): string
            {
                return $this->prefix;
            }

            public function numeric(): int
            {
                return $this->numeric;
            }
        };

        JasaraUuid::useMap([
            $generateType(10, 'prod'),
            $generateType(12, 'cus')
        ]);

        expect(JasaraUuid::getMap())->toMatchArray([
            10 => 'prod',
            12 => 'cus',
        ]);
    });
});

describe('generate', function () {

    it('generates valid uuid')
        ->expect(fn () => (new Ramsey\Uuid\Rfc4122\Validator())->validate((string) JasaraUuid::generate(random_int(0, 4095))))
        ->toBeTrue();

    it('generates version 8 uuid')
        ->expect(fn () => (string) JasaraUuid::generate(0))
        ->toMatch('/[0-9a-f]{8}-[0-9a-f]{4}-8[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{8}/');

    it('generates uuid for given type', function (int $type) {
        $uuid = JasaraUuid::generate($type);

        $typeHex = str_pad(dechex($type), 3, "0", STR_PAD_LEFT);

        expect((string) $uuid)
            ->toMatch("/[0-9a-f]{8}-[0-9a-f]{4}-8$typeHex-[0-9a-f]{4}-[0-9a-f]{8}/");
    })->with([0, 10, 530, 4095]);

    test('bits 96 and 97 are always zero')
        ->expect(fn () => (string) JasaraUuid::generate(random_int(0, 4095)))
        ->toMatch("/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}[0-3][0-9a-f]{3}/");

    // 2024-05-19 06:35:10.391 UTC in Unix Epoch (hex) = 018f 8f8f 8f8f
    it('generates uuid for type from type and timestamp')
        ->expect(fn () => (string) JasaraUuid::generate(0xabc, new DateTime("2024-05-19 06:35:01.391")))
        ->toMatch('/018f8f8f-8f8f-8abc-[0-9a-f]{4}-[0-9a-f]{12}/');

    it('fails to generate for type outside 0 - 4095')
        ->with([-1000, -1, 4096, 5000, 20000])
        ->expect(fn ($type) => fn () => JasaraUuid::generate($type))
        ->toThrow(JasaraUuidException::class);

    it('can take JasaraUuidType for $type', function () {
        $jasaraUuidType = new class () implements JasaraUuidType {
            public function numeric(): int
            {
                return 0x101;
            }
            public function prefix(): string
            {
                return 'card';
            }
        };
        JasaraUuid::useMap([$jasaraUuidType]);

        expect((string) JasaraUuid::generate($jasaraUuidType))
            ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-8101-[0-9a-f]{4}-[0-9a-f]{12}$/');

    });
});

describe('prefixed', function () {
    beforeEach(function () {
        JasaraUuid::useMap([
            0 => 'usr',
            1 => 'ord',
            2 => 'sh',
        ]);
    });

    it('returns prefixed base32hex unpadded encoded string')
        ->expect(fn () => JasaraUuid::generate(0)->prefixed())
        ->toMatch('/usr_[0-9a-v]{22}/');

    it('fails if type is not defined')
        ->expect(fn () => fn () => JasaraUuid::generate(20)->prefixed())
        ->toThrow(JasaraUuidException::class, "Type '20' does not have a corresponding prefix value.");
});

describe('fromPrefixed', function () {

    beforeEach(function () {
        JasaraUuid::useMap([
            0 => 'usr',
            1 => 'ord',
            2 => 'sh',
        ]);
    });

    it('returns same uuid', function () {

        $uuid = JasaraUuid::generate(2);

        $prefixed = $uuid->prefixed();

        $fromPrefixed = JasaraUuid::fromPrefixed($prefixed);

        expect((string) $fromPrefixed)->toEqual((string)$uuid);
    });

    it('fails if prefix is not defined')
        ->expect(fn () => fn () => JasaraUuid::fromPrefixed('proj_aj81um6u90h7g1h709k02p'))
        ->toThrow(JasaraUuidException::class);

    it('fails if ill formatted')
        ->with([
            '123',
            'ss_1',
            'abcd_xxx',
            'abcd_abcd_abcd_abcd_abcd_ab',
            'usr_usr_74pl5s7s4q6m01h70c3ttt',
            'usr_000074pl5s7s4q6m01h70c3ttt',
            ])
        ->expect(fn ($prefixed) => fn () => JasaraUuid::fromPrefixed($prefixed))
        ->toThrow(JasaraUuidException::class);
});
