<?php

namespace Lunar\Storefront\Actions\Catalog;

use Lunar\Catalog\DataObjects\PricingResponse;
use Lunar\Storefront\Data\Currency;

class GetQuantifiedPrice
{
    public function get(PricingResponse $pricingResponse, $quantity = 1): \Lunar\Storefront\Data\Price
    {
        $price = $pricingResponse->matched;

        $unitPrice = $price->price;
        $quantifiedPrice = (int) round($unitPrice * $quantity);

        return new \Lunar\Storefront\Data\Price(
            exclTax: $quantifiedPrice,
            inclTax: $quantifiedPrice,
            comparePriceExcTax: null,
            comparePriceIncTax: null,
            formattedExclTax: null,
            formattedInclTax: null,
            formattedComparePriceExcTax: null,
            formattedComparePriceIncTax: null,
            minQuantity: $price->min_quantity,
            currency: Currency::from($price->currency),
        );
    }
}
