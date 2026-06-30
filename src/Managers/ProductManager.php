<?php

namespace Lunar\Storefront\Managers;

use Illuminate\Support\Collection;
use Lunar\Core\Enums\ProductAssociation;
use Lunar\Core\Models\Product;
use Lunar\Storefront\Actions\Catalog\GetProductAssociations;
use Lunar\Storefront\Actions\Catalog\GetProductBySlug;
use Lunar\Storefront\Actions\Catalog\GetProductOptionPermutations;
use Lunar\Storefront\Actions\Catalog\GetProductOptions;

class ProductManager implements \Lunar\Storefront\Contracts\ProductManager
{
    public function getModelBySlug(string $slug): Product
    {
        return (new GetProductBySlug)->get($slug, asModel: true);
    }

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
        $associations = (new GetProductAssociations)->get($product, type: $association);

        return \Lunar\Storefront\Data\Product::collect(
            $associations->pluck('target')->filter()
        );
    }
}
