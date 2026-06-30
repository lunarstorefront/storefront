<?php

namespace Lunar\Storefront\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PriceBreak extends Data
{
    public function __construct(
        public Price $price,
        public int $lowerLimit,
        public ?int $upperLimit,
    ) {}
}
