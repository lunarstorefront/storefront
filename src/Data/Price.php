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
     * The price for the given quantity, with every figure resolved through
     * the priceable's own tax class rather than copied from the stored one.
     */
    public static function fromModel(PriceModel $price, int $quantity = 1): self
    {
        $currency = $price->resolveCurrency();
        $times = fn (PriceValue $value) => new PriceValue((int) round($value->value * $quantity), $currency);

        $exclTax = $times($price->priceExTax());
        $inclTax = $times($price->priceIncTax());

        $hasComparePrice = (bool) $price->list_price;
        $compareExclTax = $hasComparePrice ? $times(self::listPriceExTax($price)) : null;
        $compareInclTax = $hasComparePrice ? $times($price->listPriceIncTax()) : null;

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
            currency: Currency::from($currency),
            hasComparePrice: $hasComparePrice,
        );
    }

    /**
     * Core has no listPriceExTax() yet, so run the list price through
     * priceExTax() on a clone. That keeps the tax rate lookup and the
     * stored-inclusive check in core. The clone gets its own attributes and
     * shares the loaded relations, so the original is untouched.
     */
    protected static function listPriceExTax(PriceModel $price): PriceValue
    {
        if (method_exists($price, 'listPriceExTax')) {
            return $price->listPriceExTax();
        }

        return (clone $price)->forceFill(['price' => $price->list_price])->priceExTax();
    }
}
