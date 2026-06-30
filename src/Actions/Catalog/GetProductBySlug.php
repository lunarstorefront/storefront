<?php

namespace Lunar\Storefront\Actions\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Lunar\Core\Models\Product;
use Lunar\Core\States\Product\Published;

class GetProductBySlug
{
    public function get(string $slug, bool $asModel = false): Product|\Lunar\Storefront\Data\Product
    {
        $product = Product::with([
            'productType.mappedAttributes',
            'media',
            'thumbnail',
        ])->whereState('status', Published::class)
            ->whereHas('defaultUrl', fn (Builder $query) => $query->where('slug', $slug))->firstOrFail();

        return $asModel ? $product : \Lunar\Storefront\Data\Product::from($product);
    }
}
