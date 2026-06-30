<?php

namespace Lunar\Storefront\Actions\Catalog;

use Lunar\Core\DataObjects\PriceValue;
use Lunar\Core\DataObjects\PricingResponse;
use Lunar\Storefront\Data\Currency;
use Lunar\Storefront\Data\Price;

class GetQuantifiedPrice
{
    public function get(PricingResponse $pricingResponse, int $quantity = 1): ?Price
    {
        $price = $pricingResponse->matched;

        $currency = $price->resolveCurrency();

        $excTax = (int) round($price->priceExTax()->value * $quantity);
        $incTax = (int) round($price->priceIncTax()->value * $quantity);

        $hasComparePrice = $price->list_price !== null;
        $compareExcTax = $hasComparePrice ? (int) round($price->list_price * $quantity) : null;
        $compareIncTax = $hasComparePrice ? (int) round($price->listPriceIncTax()->value * $quantity) : null;

        $excValue = new PriceValue($excTax, $currency);
        $incValue = new PriceValue($incTax, $currency);
        $compareExcValue = $compareExcTax !== null ? new PriceValue($compareExcTax, $currency) : null;
        $compareIncValue = $compareIncTax !== null ? new PriceValue($compareIncTax, $currency) : null;

        return new Price(
            exclTax: $excTax,
            inclTax: $incTax,
            comparePriceExcTax: $compareExcTax,
            comparePriceIncTax: $compareIncTax,
            formattedExclTax: $excValue->format(),
            formattedInclTax: $incValue->format(),
            formattedComparePriceExcTax: $compareExcValue?->format(),
            formattedComparePriceIncTax: $compareIncValue?->format(),
            minQuantity: $price->min_quantity,
            currency: Currency::from($currency),
            hasComparePrice: $hasComparePrice,
        );
    }
}
