<?php

namespace Lunar\Storefront\Data;

use Lunar\Storefront\Data\Traits\HasAttributeData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SearchQueryHit extends Data
{
    use HasAttributeData;

    public function __construct(
        public string $term,
    ) {}
}
