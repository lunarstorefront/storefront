<?php

namespace Lunar\Storefront\Actions\Catalog;

use Lunar\Base\DataTransferObjects\PricingResponse;
use Lunar\DataTypes\Price;
use Lunar\Storefront\Data\Currency;

class GetQuantifiedPrice
{
    public function get(PricingResponse $pricingResponse, $quantity = 1): \Lunar\Storefront\Data\Price
    {
        $price = $pricingResponse->matched;

        $unitPriceIncTax = $price->priceIncTax();
        $unitPriceExTax = $price->priceExTax();

        $quantifiedPriceIncTax = (int) round($unitPriceIncTax->unitDecimal(rounding: false) * $quantity * 100);
        $quantifiedPriceExTax = (int) round($unitPriceExTax->unitDecimal(rounding: false) * $quantity * 100);

        $quantifiedIncTax = new Price(
            $quantifiedPriceIncTax,
            $price->currency,
            1,
        );

        $quantifiedExTax = new Price(
            $quantifiedPriceExTax,
            $price->currency,
            1,
        );

        return new \Lunar\Storefront\Data\Price(
            exclTax: $quantifiedExTax,
            inclTax: $quantifiedIncTax,
            comparePriceExcTax: null,
            comparePriceIncTax: null,
            minQuantity: $price->min_quantity,
            currency: Currency::from($price->currency),
        );
    }
}
