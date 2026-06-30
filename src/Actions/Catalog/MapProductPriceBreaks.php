<?php

namespace Lunar\Storefront\Actions\Catalog;

use Illuminate\Support\Collection;
use Lunar\Core\DataObjects\PricingResponse;
use Lunar\Core\Models\Price as PriceModel;
use Lunar\Storefront\Data\Price;
use Lunar\Storefront\Data\PriceBreak;

class MapProductPriceBreaks
{
    /**
     * @return Collection<int, PriceBreak>
     */
    public function map(PricingResponse $pricing, int $min = 1): Collection
    {
        if (! $pricing->priceBreaks->count()) {
            return collect();
        }

        /** @var Collection<int, PriceModel> $priceBreaks */
        $priceBreaks = $pricing->priceBreaks->sortBy('min_quantity')->values();
        $basePrice = $pricing->base;

        $firstBreak = $priceBreaks->first();
        $firstBreakQuantity = $firstBreak?->min_quantity ?? $min;

        $baseTiers = $firstBreakQuantity > $min && $basePrice instanceof PriceModel
            ? collect([
                new PriceBreak(
                    price: Price::fromModel($basePrice),
                    lowerLimit: $min,
                    upperLimit: $firstBreakQuantity - 1,
                ),
            ])
            : collect();

        $mappedTiers = $priceBreaks
            ->map(function (PriceModel $priceBreak, int $index) use ($priceBreaks): PriceBreak {
                $upperLimit = $priceBreaks[$index + 1] ?? null;

                return new PriceBreak(
                    price: Price::fromModel($priceBreak),
                    lowerLimit: $priceBreak->min_quantity,
                    upperLimit: $upperLimit ? (int) $upperLimit->min_quantity - 1 : null,
                );
            })->values();

        return $baseTiers->merge($mappedTiers);
    }
}
