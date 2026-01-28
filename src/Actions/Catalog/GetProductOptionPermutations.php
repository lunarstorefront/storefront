<?php

namespace Lunar\Storefront\Actions\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Lunar\Models\Contracts\Product;
use Lunar\Models\Contracts\ProductOption;
use Lunar\Models\ProductOptionValue;
use Lunar\Storefront\Data\ProductOptionPermutation;
use Lunar\Storefront\Facades\Storefront;
use Lunar\Storefront\Managers\VariantManager;

class GetProductOptionPermutations
{
    /**
     * @param Collection<ProductOption> $productOptions
     * @param Product $product
     * @return Collection
     */
    public function get(Collection $productOptions, Product $product): Collection
    {
        $permutations = $productOptions
            ->pluck('values')
            ->reduce(function ($carry, $values) {
                if ($carry === null) {
                    return collect($values)->map(fn ($v) => [$v]);
                }

                return collect($carry)->flatMap(function ($combo) use ($values) {
                    return collect($values)->map(fn ($v) => array_merge($combo, [$v]));
                })->values();
            }, null) ?: collect();

        return $permutations->map(function ($values) use ($product) {
            $values = collect($values);

            $variantQuery = $product->variants()
                ->whereHas(
                    relation:'values',
                    callback: fn (Builder $query) => $query->whereIn((new ProductOptionValue)->getTable().'.id', $values->pluck('id')->all()),
                    operator: '=',
                    count: $values->count()
                );

            return new ProductOptionPermutation(
                hash: Storefront::variants()->encryptOptions($values->mapWithKeys(
                    fn ($value) => [$value->product_option_id => $value->id]
                )->toArray()),
                hasVariant: $variantQuery->exists(),
                stock: $variantQuery->sum('stock'),
                backorder: $variantQuery->clone()->where('purchasable', 'always')->exists(),
                values: $values->mapWithKeys(
                    fn ($value) => [(string) $value->product_option_id => $value->id]
                )->toArray(),
                valueNames: $values->mapWithKeys(
                    fn ($value) => [(string) $value->option->translate('name') => $value->translate('name')]
                )->toArray()
            );
        });
    }
}
