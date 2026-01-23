<?php

namespace Lunar\Storefront\Actions\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Collection;
use Lunar\Base\Enums\Concerns\ProvidesProductAssociationType;
use Lunar\Facades\StorefrontSession;
use Lunar\Models\Contracts\Product;

class GetProductAssociations
{
    public function get(Product $product, ?ProvidesProductAssociationType $type = null, bool $inverse = false): Collection
    {
        $currency = StorefrontSession::getCurrency();

        return ($inverse ? $product->inverseAssociations() : $product->associations())->when(
            $type,
            fn (Builder $query) => $query->type($type)
        )->with(
            match ($inverse) {
                true =>  [
                    'parent' => fn (BelongsTo $query) => $query->withoutTrashed(),
                    'parent.defaultUrl',
                    'parent.thumbnail' => fn (MorphOne $query) => $query->where('collection_name', config('lunar.media.collection')),
                    'parent.prices' => fn ($query) => $query->where('currency_id', $currency->id),
                    'parent.prices.currency',
                    'parent.prices.priceable',
                    'parent.media',
                ],
                false => [
                    'target' => fn (BelongsTo $query) => $query->withoutTrashed(),
                    'target.defaultUrl',
                    'target.thumbnail' => fn (MorphOne $query) => $query->where('collection_name', config('lunar.media.collection')),
                    'target.prices' => fn ($query) => $query->where('currency_id', $currency->id),
                    'target.prices.currency',
                    'target.prices.priceable',
                    'target.media',
                ]
            }
        )->get();
    }
}
