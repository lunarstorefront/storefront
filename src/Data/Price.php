<?php

namespace Lunar\Storefront\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/** @typescript */
class Price extends Data
{
    public function __construct(
        public \Lunar\DataTypes\Price $exclTax,
        public \Lunar\DataTypes\Price $inclTax,
        public ?\Lunar\DataTypes\Price $comparePriceExcTax,
        public ?\Lunar\DataTypes\Price $comparePriceIncTax,
        public int $minQuantity,
        public Lazy|Currency $currency,
        public bool $hasComparePrice = false,
    ) {}

    public static function fromModel(\Lunar\Models\Contracts\Price $price): self
    {
        return new self(
            exclTax: $price->price,
            inclTax: $price->priceIncTax(),
            comparePriceExcTax: $price->compare_price,
            comparePriceIncTax: $price->comparePriceIncTax(),
            minQuantity: $price->min_quantity,
            currency: Lazy::whenLoaded('currency', $price, fn () => Currency::from($price->currency)),
            hasComparePrice: (bool) $price->compare_price?->value,
        );
    }
}
