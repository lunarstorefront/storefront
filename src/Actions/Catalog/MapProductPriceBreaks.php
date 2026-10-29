<?php

namespace Lunar\Storefront\Actions\Catalog;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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
     * lower the price is left out and logged (once a day, and only for breaks
     * the buyer can reach), so the table only advertises savings the cart
     * will actually give.
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
            // Rows below the minimum are never shown on their own, so only a
            // break the buyer could actually reach is worth reporting.
            $reachable = (int) $minQuantity >= $min;

            if ($reachable) {
                $this->warnAboutDuplicateGroupRows($rows);
            }

            /** @var PriceModel $cheapest */
            $cheapest = $rows->sortBy('price')->first();
            $lowerLimit = max((int) $minQuantity, $min);

            if ($cheapest->price >= $current->price) {
                if ($reachable && ! $cheapest->is($pricing->base)) {
                    $this->warn('Price break skipped: it does not lower the price.', $cheapest);
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
    protected function warnAboutDuplicateGroupRows(Collection $rows): void
    {
        $rows->groupBy(fn (PriceModel $row) => $row->customer_group_id ?? 'all')
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->each(fn (Collection $group) => $this->warn(
                'Price break overlaps: one customer group has several prices at the same quantity.',
                $group->first(),
            ));
    }

    /**
     * Log a data problem at most once a day. The mapper runs on every product
     * view, so without this one bad row would log a warning per page load.
     */
    protected function warn(string $message, PriceModel $row): void
    {
        $context = $this->context($row);

        if (Cache::add('price-break-warning:'.md5($message.json_encode($context)), true, now()->addDay())) {
            Log::warning($message, $context);
        }
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
