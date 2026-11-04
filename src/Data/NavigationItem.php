<?php

namespace Lunar\Storefront\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class NavigationItem extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $slug,
        /** @var NavigationItem[] */
        public array $children = [],
    ) {}
}
