<?php

namespace Lunar\Storefront\Actions\Catalog;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Lunar\Core\DataObjects\PricingResponse;
use Lunar\Core\Models\Price as PriceModel;
use Lunar\Storefront\Data\Price;
use Lunar\Storefront\Data\PriceBreak;

class MapProductPriceBreaks
{
    /**
     * One tier per quantity, priced the way Lunar's PricingManager charges it:
     * the cheapest row the quantity reaches. Rows for several customer groups
     * at one quantity collapse into the cheapest, and a tier that would not
     * lower the price is left out and logged, so the table only advertises
     * savings the cart will actually give.
     *
     * @return Collection<int, PriceBreak>
     */
    public function map(PricingResponse $pricing, int $min = 1): Collection
    {
        if (! $pricing->priceBreaks->count()) {
            return collect();
        }

        $current = $pricing->base;

        /** @var array<int, PriceModel> $tiers keyed by lower limit */
        $tiers = [$min => $current];

        foreach ($pricing->priceBreaks->groupBy('min_quantity')->sortKeys() as $minQuantity => $rows) {
            $this->logDuplicateGroupRows($rows);

            /** @var PriceModel $cheapest */
            $cheapest = $rows->sortBy('price')->first();
            $lowerLimit = max((int) $minQuantity, $min);

            if ($cheapest->price >= $current->price) {
                if (! $cheapest->is($pricing->base)) {
                    Log::warning('Price break skipped: it does not lower the price.', $this->context($cheapest));
                }

                continue;
            }

            $tiers[$lowerLimit] = $current = $cheapest;
        }

        $lowerLimits = array_keys($tiers);

        return collect($lowerLimits)->map(fn (int $lowerLimit, int $index) => new PriceBreak(
            price: Price::fromModel($tiers[$lowerLimit]),
            lowerLimit: $lowerLimit,
            upperLimit: isset($lowerLimits[$index + 1]) ? $lowerLimits[$index + 1] - 1 : null,
        ));
    }

    /**
     * @param  Collection<int, PriceModel>  $rows  the breaks at one quantity
     */
    protected function logDuplicateGroupRows(Collection $rows): void
    {
        $rows->groupBy(fn (PriceModel $row) => $row->customer_group_id ?? 'all')
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->each(fn (Collection $group) => Log::warning(
                'Price break overlaps: one customer group has several prices at the same quantity.',
                $this->context($group->first()),
            ));
    }

    /**
     * @return array{priceable_type: string, priceable_id: int, customer_group_id: ?int, min_quantity: int, price: int}
     */
    protected function context(PriceModel $row): array
    {
        return [
            'priceable_type' => $row->priceable_type,
            'priceable_id' => $row->priceable_id,
            'customer_group_id' => $row->customer_group_id,
            'min_quantity' => (int) $row->min_quantity,
            'price' => $row->price,
        ];
    }
}
