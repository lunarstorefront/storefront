<?php

namespace Lunar\Storefront\Listeners;

use Illuminate\Database\Eloquent\Collection;
use Lunar\Core\Events\Orders\OrderPlaced;
use Lunar\Core\Models\OrderLine;
use Lunar\Core\Models\ProductVariant;

/**
 * Records each product line's manufacturer part number on its meta as the
 * order is placed, so the order keeps the part number it was sold under, as
 * Lunar already does for the SKU and description. A variant with no part
 * number is recorded as null.
 *
 * This runs on OrderPlaced rather than in the order creation pipeline: that
 * pipeline runs again on the draft whenever checkout is retried and matches
 * order lines to cart lines by their meta. Lines that already carry an `mpn`
 * key are left alone.
 */
class StampOrderLinePartNumbers
{
    public function handle(OrderPlaced $event): void
    {
        /** @var Collection<int, OrderLine> $lines */
        $lines = $event->order->productLines()->with('purchasable')->get();

        $lines->reject(fn (OrderLine $line) => array_key_exists('mpn', (array) $line->meta))
            ->each(function (OrderLine $line): void {
                $line->meta = [
                    ...(array) $line->meta,
                    'mpn' => $line->purchasable instanceof ProductVariant ? $line->purchasable->mpn : null,
                ];
                $line->save();
            });
    }
}
