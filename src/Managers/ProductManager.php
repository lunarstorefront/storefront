<?php

namespace Lunar\Storefront\Managers;

use Illuminate\Support\Collection;
use Lunar\Base\Enums\ProductAssociation;
use Lunar\Models\Contracts\Product;
use Lunar\Storefront\Actions\Catalog\GetProductAssociations;
use Lunar\Storefront\Actions\Catalog\GetProductOptionPermutations;
use Lunar\Storefront\Actions\Catalog\GetProductOptions;

class ProductManager implements \Lunar\Storefront\Contracts\ProductManager
{
    public function getPermutations(Product $product, ?Collection $options = null): Collection
    {
        return (new GetProductOptionPermutations)->get(
            $options ?: $this->getOptions($product),
            $product
        );
    }

    public function getOptions(Product $product): Collection
    {
        return (new GetProductOptions)->get($product);
    }

    public function getAssociations(Product $product, ProductAssociation $association): Collection
    {
        $associations = (new GetProductAssociations)->get($product);

        return \Lunar\Storefront\Data\Product::collect(
            $associations->filter(function ($association) {
                return $association->type === $association->value;
            })->pluck('target')
        );
    }
}
