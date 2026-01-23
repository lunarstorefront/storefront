<?php

namespace Lunar\Storefront\Actions\Catalog;

use Illuminate\Support\Collection;
use Lunar\Base\DataTransferObjects\PricingResponse;
use Lunar\Storefront\Data\Price;
use Lunar\Storefront\Data\PriceBreak;

class MapProductPriceBreaks
{
    public function map(PricingResponse $pricing, $min = 1): Collection
    {
        if (! $pricing->priceBreaks->count()) {
            return collect();
        }

        $priceBreaks = $pricing->priceBreaks->sortBy('min_quantity');
        $basePrice = $pricing->base;

        $baseTiers = collect([
            new PriceBreak(
                price: Price::from($basePrice),
                lowerLimit: $min,
                upperLimit: $priceBreaks->first()->min_quantity - 1,
            ),
        ]);

        $mappedTiers = $priceBreaks
            ->map(function ($priceBreak, $index) use ($priceBreaks) {
                $upperLimit = $priceBreaks[$index + 1] ?? null;

                return new PriceBreak(
                    price: Price::from($priceBreak),
                    lowerLimit: $priceBreak->min_quantity,
                    upperLimit: $upperLimit ? $upperLimit->min_quantity - 1 : null,
                );
            })->values();

        return $baseTiers->merge($mappedTiers);
    }
}
