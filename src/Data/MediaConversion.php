<?php

namespace Lunar\Storefront\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class MediaConversion extends Data
{
    public function __construct(
        public string $name,
        public string $url,
    ) {}
}
