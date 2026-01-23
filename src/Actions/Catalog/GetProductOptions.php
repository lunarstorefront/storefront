<?php

namespace Lunar\Storefront\Actions\Catalog;

use Illuminate\Support\Collection;
use Lunar\Models\Contracts\Product;
use Lunar\Storefront\Data\ProductOption;

class GetProductOptions
{
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
