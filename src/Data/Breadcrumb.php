<?php

namespace Lunar\Storefront\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class Breadcrumb extends Data
{
    public function __construct(
        public string $label,
        public string $model,
        public string $slug,
    ) {}
}
