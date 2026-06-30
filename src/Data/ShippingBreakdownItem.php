<?php

namespace Lunar\Storefront\Data;

use Lunar\Core\ValueObjects\Cart\ShippingBreakdownItem as ShippingBreakdownItemValueObject;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ShippingBreakdownItem extends Data
{
    public function __construct(
        public string $name,
        public string $identifier,
        public int $price,
    ) {}

    public static function fromValueObject(ShippingBreakdownItemValueObject $item): self
    {
        return new self(
            name: $item->name,
            identifier: $item->identifier,
            price: $item->price->value,
        );
    }
}
