<?php

namespace Lunar\Storefront\Data;

use Lunar\Core\DataObjects\PriceValue;
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

    /**
     * A single unit price, with the inclusive figure resolved from the
     * priceable's own tax class (as GetQuantifiedPrice does), not copied
     * from the stored exclusive one.
     */
    public static function fromModel(PriceModel $price): self
    {
        $exclTax = $price->priceExTax();
        $inclTax = $price->priceIncTax();

        $hasComparePrice = (bool) $price->list_price;
        $compareExclTax = $hasComparePrice ? new PriceValue((int) $price->list_price, $price->resolveCurrency()) : null;
        $compareInclTax = $hasComparePrice ? $price->listPriceIncTax() : null;

        return new self(
            exclTax: $exclTax->value,
            inclTax: $inclTax->value,
            comparePriceExcTax: $compareExclTax?->value,
            comparePriceIncTax: $compareInclTax?->value,
            formattedExclTax: $exclTax->format(),
            formattedInclTax: $inclTax->format(),
            formattedComparePriceExcTax: $compareExclTax?->format(),
            formattedComparePriceIncTax: $compareInclTax?->format(),
            minQuantity: $price->min_quantity,
            currency: Lazy::whenLoaded('currency', $price, fn () => Currency::from($price->currency)),
            hasComparePrice: $hasComparePrice,
        );
    }
}
