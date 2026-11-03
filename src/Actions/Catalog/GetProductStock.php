<?php

namespace Lunar\Storefront\Actions\Catalog;

use Lunar\Core\Enums\SellingPolicy;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductVariant;
use Lunar\Storefront\Data\ProductStock;

final class GetProductStock
{
    /**
     * Stock state of a variant (the product's lowest-id one by default):
     * out of stock when it cannot be fulfilled at quantity 1, in stock above
     * the low threshold, low stock above zero, otherwise backorder.
     */
    public function handle(Product $product, ?ProductVariant $variant = null): ProductStock
    {
        $showQuantity = (bool) config('storefront.stock.show_quantity', false);

        $variant ??= $product->loadMissing('variants')->variants->sortBy('id')->first();

        if (! $variant instanceof ProductVariant) {
            return new ProductStock(
                state: 'out_of_stock',
                count: null,
                showQuantity: $showQuantity,
                leadTimeDays: null,
                maxQuantity: null,
            );
        }

        $available = max(0, $variant->stock_available);

        return new ProductStock(
            state: $this->state($variant, $available),
            count: $showQuantity ? $available : null,
            showQuantity: $showQuantity,
            leadTimeDays: $this->leadTimeDays($product),
            maxQuantity: $this->maxQuantity($variant),
        );
    }

    private function state(ProductVariant $variant, int $available): string
    {
        return match (true) {
            ! $variant->canBeFulfilledAtQuantity(1) => 'out_of_stock',
            $available > (int) config('storefront.stock.low_threshold', 5) => 'in_stock',
            $available > 0 => 'low_stock',
            default => 'backorder',
        };
    }

    private function leadTimeDays(Product $product): ?int
    {
        $handle = config('storefront.stock.lead_time_attribute');

        if (! is_string($handle) || $handle === '') {
            return null;
        }

        $days = (int) $product->translateAttribute($handle);

        return $days > 0 ? $days : null;
    }

    private function maxQuantity(ProductVariant $variant): ?int
    {
        if ($variant->selling_policy === SellingPolicy::Always) {
            return null;
        }

        $total = $variant->getTotalInventory();

        return $total > (int) config('storefront.stock.practical_limit', 999) ? null : max(0, $total);
    }
}
