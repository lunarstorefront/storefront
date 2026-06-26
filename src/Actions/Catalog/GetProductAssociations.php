<?php

namespace Lunar\Storefront\Actions\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Collection;
use Lunar\Catalog\Enums\ProductAssociationType;
use Lunar\Catalog\Models\Product;
use Lunar\Sales\Facades\CartSession;

class GetProductAssociations
{
    /**
     * @return Collection<int, \Lunar\Catalog\Models\ProductAssociation>
     */
    public function get(Product $product, ?ProductAssociationType $type = null, bool $inverse = false): Collection
    {
        $currency = CartSession::getCurrency();
        $currencyId = $currency?->id;

        return ($inverse ? $product->inverseAssociations() : $product->associations())->when(
            $type,
            fn (Builder $query): Builder => $query->type($type)
        )->with(
            match ($inverse) {
                true =>  [
                    'parent',
                    'parent.defaultUrl',
                    'parent.thumbnail' => fn (MorphOne $query) => $query->where('collection_name', config('lunar.media.collection')),
                    'parent.prices' => fn ($query) => $query->where('currency_id', $currencyId),
                    'parent.prices.currency',
                    'parent.prices.priceable',
                    'parent.media',
                ],
                false => [
                    'target',
                    'target.defaultUrl',
                    'target.thumbnail' => fn (MorphOne $query) => $query->where('collection_name', config('lunar.media.collection')),
                    'target.prices' => fn ($query) => $query->where('currency_id', $currencyId),
                    'target.prices.currency',
                    'target.prices.priceable',
                    'target.media',
                ]
            }
        )->get();
    }
}
