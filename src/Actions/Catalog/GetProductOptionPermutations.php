<?php

namespace Lunar\Storefront\Actions\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductOption;
use Lunar\Core\Models\ProductOptionValue;
use Lunar\Storefront\Data\ProductOptionPermutation;
use Lunar\Storefront\Facades\Storefront;

class GetProductOptionPermutations
{
    /**
     * @param  Collection<int, ProductOption>  $productOptions
     * @return Collection<int, ProductOptionPermutation>
     */
    public function get(Collection $productOptions, Product $product): Collection
    {
        $permutations = $productOptions
            ->pluck('values')
            ->reduce(function (?Collection $carry, mixed $values): Collection {
                /** @var iterable<int, ProductOptionValue> $values */
                if ($carry === null) {
                    return collect($values)->map(fn (ProductOptionValue $v): array => [$v]);
                }

                return $carry->flatMap(function (mixed $combo) use ($values): Collection {
                    /** @var array<int, ProductOptionValue> $combo */
                    return collect($values)->map(fn (ProductOptionValue $v): array => array_merge($combo, [$v]));
                })->values();
            }, null) ?: collect();

        return $permutations->map(function (mixed $values) use ($product): ProductOptionPermutation {
            /** @var Collection<int, ProductOptionValue> $values */
            $values = collect($values);

            $variantQuery = $product->variants()
                ->whereHas(
                    relation: 'values',
                    callback: fn (Builder $query) => $query->whereIn((new ProductOptionValue)->getTable().'.id', $values->pluck('id')->all()),
                    operator: '=',
                    count: $values->count()
                );

            return new ProductOptionPermutation(
                hash: Storefront::variants()->encryptOptions($values->mapWithKeys(
                    fn (ProductOptionValue $value) => [$value->product_option_id => $value->id]
                )->toArray()),
                hasVariant: $variantQuery->exists(),
                stock: (int) $variantQuery->sum('stock'),
                backorder: $variantQuery->clone()->where('purchasable', 'always')->exists(),
                values: $values->mapWithKeys(
                    fn (ProductOptionValue $value) => [(string) $value->product_option_id => $value->id]
                )->toArray(),
                valueNames: $values->mapWithKeys(
                    fn (ProductOptionValue $value) => [(string) $value->option->translate('name') => (string) $value->translate('name')]
                )->toArray()
            );
        });
    }
}
