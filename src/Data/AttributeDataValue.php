<?php

namespace Lunar\Storefront\Data;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AttributeDataValue
{
    public function __construct(
        public string $name,
        public string $handle,
        public string $type,
        public mixed $value,
    ) {}
}
