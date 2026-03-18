<?php

namespace Lunar\Storefront\Actions\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Lunar\Catalog\Models\Product;
use Lunar\Catalog\States\Product\Active;

class GetProductBySlug
{
    public function get(string $slug, bool $asModel = false)
    {
        $product = Product::with([
            'productType.productBlueprint',
            'media',
            'thumbnail' => fn (MorphOne $query) => $query->where('collection_name', config('lunar.media.collection')),
        ])->whereState('status', Active::class)
            ->whereHas('defaultUrl', fn (Builder $query) => $query->where('slug', $slug))->firstOrFail();

        return $asModel ? $product : \Lunar\Storefront\Data\Product::from($product);
    }
}
