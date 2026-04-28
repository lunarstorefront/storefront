<?php

namespace Lunar\Storefront\Data;

use Lunar\Catalog\DataObjects\TaxAwarePrice;
use Lunar\Catalog\Models\Price as PriceModel;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/** @typescript */
class Price extends Data
{
    public function __construct(
        public int $exclTax,
        public int $inclTax,
        public ?int $comparePriceExcTax,
        public ?int $comparePriceIncTax,
        public ?string $formattedExclTax,
        public ?string $formattedInclTax,
        public ?string $formattedComparePriceExcTax,
        public ?string $formattedComparePriceIncTax,
        public int $minQuantity,
        public Lazy|Currency $currency,
        public bool $hasComparePrice = false,
    ) {}

    public static function fromModel(PriceModel $price): self
    {
        return new self(
            exclTax: $price->price,
            inclTax: $price->price,
            comparePriceExcTax: $price->compare_price,
            comparePriceIncTax: $price->compare_price,
            formattedExclTax: $price->format('price'),
            formattedInclTax: $price->format('price'),
            formattedComparePriceExcTax: null,
            formattedComparePriceIncTax: null,
            minQuantity: $price->min_quantity,
            currency: Lazy::whenLoaded('currency', $price, fn () => Currency::from($price->currency)),
            hasComparePrice: (bool) $price->compare_price,
        );
    }

    public static function fromTaxAwarePrice(TaxAwarePrice $taxAwarePrice, int $minQuantity, Lazy|Currency $currency): self
    {
        return new self(
            exclTax: $taxAwarePrice->priceExcTax->value,
            inclTax: $taxAwarePrice->priceIncTax->value,
            comparePriceExcTax: $taxAwarePrice->comparePriceExcTax?->value,
            comparePriceIncTax: $taxAwarePrice->comparePriceIncTax?->value,
            formattedExclTax: $taxAwarePrice->priceExcTax->format(),
            formattedInclTax: $taxAwarePrice->priceIncTax->format(),
            formattedComparePriceExcTax: $taxAwarePrice->comparePriceExcTax?->format(),
            formattedComparePriceIncTax: $taxAwarePrice->comparePriceIncTax?->format(),
            minQuantity: $minQuantity,
            currency: $currency,
            hasComparePrice: $taxAwarePrice->comparePriceExcTax !== null,
        );
    }
}
