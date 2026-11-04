<?php

namespace Lunar\Storefront\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ProductStock extends Data
{
    public function __construct(
        #[LiteralTypeScriptType("'in_stock' | 'low_stock' | 'backorder' | 'out_of_stock'")]
        public string $state,
        /** Units available now; only set when the store shows quantities. */
        public ?int $count,
        /** Whether the page shows the count beside the state. */
        public bool $showQuantity,
        /** Lead time in days for a product sold from backorder; null when unknown. */
        public ?int $leadTimeDays,
        /** Most the cart will accept; null when there is no practical limit. */
        public ?int $maxQuantity,
    ) {}
}
