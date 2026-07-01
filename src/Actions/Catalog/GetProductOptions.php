<?php

namespace Lunar\Storefront\Actions\Catalog;

use Illuminate\Support\Collection;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductOption;

class GetProductOptions
{
    /**
     * @return Collection<int, ProductOption>
     */
    public function get(Product $product): Collection
    {
        return $product->productOptions()->with([
            'values.option',
            'values' => function ($query) use ($product) {
                $query->whereHas('variants', function ($relation) use ($product) {
                    $relation->where('product_id', $product->id);
                });
            },
        ])->get();
    }
}
