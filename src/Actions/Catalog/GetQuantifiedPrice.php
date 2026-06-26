<?php

namespace Lunar\Storefront\Actions\Catalog;

use Lunar\Catalog\DataObjects\PricingResponse;
use Lunar\Catalog\DataObjects\TaxAwarePrice;
use Lunar\Catalog\DataObjects\TaxAwarePricingResponse;
use Lunar\Catalog\Models\Price as PriceModel;
use Lunar\Kernel\DataObjects\PriceValue;
use Lunar\Storefront\Data\Currency;
use Lunar\Storefront\Data\Price;
use RuntimeException;

class GetQuantifiedPrice
{
    public function get(PricingResponse|TaxAwarePricingResponse $pricingResponse, int $quantity = 1): Price
    {
        if ($pricingResponse instanceof TaxAwarePricingResponse) {
            return $this->fromTaxAware($pricingResponse, $quantity);
        }

        $price = $pricingResponse->matched;

        if (! $price instanceof PriceModel) {
            throw new RuntimeException('Pricing response is missing a matched price.');
        }

        $currency = $price->resolveCurrency();
        $unitPrice = $price->price;
        $quantifiedPrice = (int) round($unitPrice * $quantity);

        $priceValue = new PriceValue($quantifiedPrice, $currency);

        return new Price(
            exclTax: $quantifiedPrice,
            inclTax: $quantifiedPrice,
            comparePriceExcTax: null,
            comparePriceIncTax: null,
            formattedExclTax: $priceValue->format(),
            formattedInclTax: $priceValue->format(),
            formattedComparePriceExcTax: null,
            formattedComparePriceIncTax: null,
            minQuantity: $price->min_quantity,
            currency: Currency::from($currency),
        );
    }

    protected function fromTaxAware(TaxAwarePricingResponse $response, int $quantity): Price
    {
        $matched = $response->matched;

        if (! $matched instanceof TaxAwarePrice) {
            throw new RuntimeException('Tax aware pricing response is missing a matched price.');
        }

        $currency = $matched->priceExcTax->resolveCurrency();

        $excTax = (int) round($matched->priceExcTax->value * $quantity);
        $incTax = (int) round($matched->priceIncTax->value * $quantity);
        $compareExc = $matched->comparePriceExcTax !== null
            ? (int) round($matched->comparePriceExcTax->value * $quantity)
            : null;
        $compareInc = $matched->comparePriceIncTax !== null
            ? (int) round($matched->comparePriceIncTax->value * $quantity)
            : null;

        $excValue = new PriceValue($excTax, $currency);
        $incValue = new PriceValue($incTax, $currency);
        $compareExcValue = $compareExc !== null ? new PriceValue($compareExc, $currency) : null;
        $compareIncValue = $compareInc !== null ? new PriceValue($compareInc, $currency) : null;

        return new Price(
            exclTax: $excTax,
            inclTax: $incTax,
            comparePriceExcTax: $compareExc,
            comparePriceIncTax: $compareInc,
            formattedExclTax: $excValue->format(),
            formattedInclTax: $incValue->format(),
            formattedComparePriceExcTax: $compareExcValue?->format(),
            formattedComparePriceIncTax: $compareIncValue?->format(),
            minQuantity: 1,
            currency: Currency::from($currency),
            hasComparePrice: $compareExc !== null,
        );
    }
}
