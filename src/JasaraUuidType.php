<?php

declare(strict_types=1);

namespace Jasara\Uuid;

interface JasaraUuidType
{
    public function prefix(): string;

    public function numeric(): int;
}
