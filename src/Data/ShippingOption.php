<?php

namespace Lunar\Storefront\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ShippingOption extends Data
{
    public function __construct(
        public string $name,
        public ?string $description,
        public ?string $identifier,
        public int $price,
        public ?string $cutoff = null,
    ) {}
}
