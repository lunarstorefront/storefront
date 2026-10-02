<?php

namespace Lunar\Storefront\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One line of a past order as a reorder saw it: added to the cart, or skipped
 * with the reason a customer can be told.
 */
#[TypeScript]
class ReorderLine extends Data
{
    public const string UNAVAILABLE = 'unavailable';

    public const string OUT_OF_STOCK = 'out_of_stock';

    public const string QUANTITY = 'quantity';

    public function __construct(
        public string $identifier,
        public string $name,
        public int $quantity,
        /** One of the class constants when skipped, null when added. */
        public ?string $reason = null,
    ) {}
}
