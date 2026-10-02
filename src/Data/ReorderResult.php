<?php

namespace Lunar\Storefront\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ReorderResult extends Data
{
    public function __construct(
        /** @var ReorderLine[] */
        public array $added = [],
        /** @var ReorderLine[] */
        public array $skipped = [],
    ) {}
}
