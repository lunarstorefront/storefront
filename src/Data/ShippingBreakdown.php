<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection;
use Lunar\Core\ValueObjects\Cart\ShippingBreakdown as ShippingBreakdownValueObject;
use Lunar\Core\ValueObjects\Cart\ShippingBreakdownItem as ShippingBreakdownItemValueObject;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ShippingBreakdown extends Data
{
    public function __construct(
        /** @var ShippingBreakdownItem[] */
        public Collection $items,
    ) {}

    public static function fromValueObject(ShippingBreakdownValueObject $breakdown): self
    {
        return new self(
            items: ($breakdown->items ?? collect())
                ->map(fn (ShippingBreakdownItemValueObject $item) => ShippingBreakdownItem::fromValueObject($item))
                ->values(),
        );
    }
}
