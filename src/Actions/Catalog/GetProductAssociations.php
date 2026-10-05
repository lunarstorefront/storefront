<?php

namespace Lunar\Storefront\Actions\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Collection;
use Lunar\Core\Enums\ProductAssociation;
use Lunar\Core\Facades\CartSession;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductAssociation as ProductAssociationModel;

class GetProductAssociations
{
    /**
     * @return Collection<int, ProductAssociationModel>
     */
    public function get(Product $product, ?ProductAssociation $type = null, bool $inverse = false): Collection
    {
        return ($inverse ? $product->inverseAssociations() : $product->associations())->when(
            $type,
            fn (Builder $query): Builder => $query->type($type)
        )->with($this->relations($inverse))->get();
    }

    /**
     * The associations of several products in one query, such as everything
     * in a basket, so the query count does not grow with the products.
     *
     * Ordered by the products as given, then by each product's own
     * association order (sort, then id), so the first product's associations
     * come first.
     *
     * @param  iterable<Product>  $products
     * @return Collection<int, ProductAssociationModel>
     */
    public function getForProducts(iterable $products, ?ProductAssociation $type = null, bool $inverse = false): Collection
    {
        $ids = collect($products)->map(fn (Product $product): int => (int) $product->getKey())->unique()->values();

        if ($ids->isEmpty()) {
            return new EloquentCollection;
        }

        $key = $inverse ? 'product_target_id' : 'product_parent_id';
        $position = $ids->flip();

        return ProductAssociationModel::query()
            ->whereIn($key, $ids->all())
            ->when($type, fn (Builder $query): Builder => $query->type($type))
            ->with($this->relations($inverse))
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (ProductAssociationModel $association): int => $position[(int) $association->getAttribute($key)])
            ->values();
    }

    /**
     * What a product card reads from the associated product: its URL,
     * thumbnail, prices in the session currency and media.
     *
     * @return array<int|string, mixed>
     */
    protected function relations(bool $inverse): array
    {
        $currencyId = CartSession::getCurrency()?->id;
        $relation = $inverse ? 'parent' : 'target';

        return [
            $relation,
            "{$relation}.defaultUrl",
            "{$relation}.thumbnail" => fn (MorphOne $query) => $query->where('collection_name', config('lunar.media.collection')),
            "{$relation}.prices" => fn ($query) => $query->where('currency_id', $currencyId),
            "{$relation}.prices.currency",
            "{$relation}.prices.priceable",
            "{$relation}.media",
        ];
    }
}
