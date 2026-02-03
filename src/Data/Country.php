<?php

namespace Lunar\Storefront\Data;

use Spatie\LaravelData\Data;

/** @typescript */
class Country extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $iso2,
        public string $emoji
    ) {}
}
