<?php

namespace Lunar\Storefront\Actions\Catalog;

use Illuminate\Support\Collection;
use Lunar\Catalog\DataObjects\PricingResponse;
use Lunar\Storefront\Data\Price;
use Lunar\Storefront\Data\PriceBreak;

class MapProductPriceBreaks
{
    public function map(PricingResponse $pricing, $min = 1): Collection
    {
        if (! $pricing->priceBreaks->count()) {
            return collect();
        }

        $priceBreaks = $pricing->priceBreaks->sortBy('min_quantity')->values();
        $basePrice = $pricing->base;

        $firstBreakQuantity = $priceBreaks->first()->min_quantity;

        $baseTiers = $firstBreakQuantity > $min
            ? collect([
                new PriceBreak(
                    price: Price::fromModel($basePrice),
                    lowerLimit: $min,
                    upperLimit: $firstBreakQuantity - 1,
                ),
            ])
            : collect();

        $mappedTiers = $priceBreaks
            ->map(function ($priceBreak, $index) use ($priceBreaks) {
                $upperLimit = $priceBreaks[$index + 1] ?? null;

                return new PriceBreak(
                    price: Price::fromModel($priceBreak),
                    lowerLimit: $priceBreak->min_quantity,
                    upperLimit: $upperLimit ? $upperLimit->min_quantity - 1 : null,
                );
            })->values();

        return $baseTiers->merge($mappedTiers);
    }
}
