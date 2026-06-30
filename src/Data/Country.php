<?php

namespace Lunar\Storefront\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class Country extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $iso2,
        public string $emoji
    ) {}
}
