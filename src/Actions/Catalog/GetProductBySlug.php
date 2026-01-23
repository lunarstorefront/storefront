<?php

namespace Lunar\Storefront\Actions\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Lunar\Models\Product;
use Lunar\Storefront\Data\Collection;

class GetProductBySlug
{
    public function get(string $slug, bool $asModel = false)
    {
        $product = Product::with([
            'productType.mappedAttributes',
            'images',
            'thumbnail' => fn (MorphOne $query) => $query->where('collection_name', config('lunar.media.collection')),
        ])->status('published')
            ->whereHas('defaultUrl', fn (Builder $query) => $query->where('slug', $slug))->firstOrFail();

        return $asModel ? $product : \Lunar\Storefront\Data\Product::from($product);
    }
}
