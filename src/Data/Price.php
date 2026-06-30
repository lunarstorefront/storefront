<?php

namespace Lunar\Storefront\Data;

use Lunar\Core\Models\Price as PriceModel;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
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
            comparePriceExcTax: $price->list_price,
            comparePriceIncTax: $price->list_price,
            formattedExclTax: $price->format('price'),
            formattedInclTax: $price->format('price'),
            formattedComparePriceExcTax: null,
            formattedComparePriceIncTax: null,
            minQuantity: $price->min_quantity,
            currency: Lazy::whenLoaded('currency', $price, fn () => Currency::from($price->currency)),
            hasComparePrice: (bool) $price->list_price,
        );
    }
}
