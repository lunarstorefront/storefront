<?php

namespace Lunar\Storefront\Contracts;

use Illuminate\Support\Collection;
use Lunar\Catalog\Enums\ProductAssociationType;
use Lunar\Catalog\Models\Product;
use Lunar\Catalog\Models\ProductOption;
use Lunar\Storefront\Data\Product as ProductData;
use Lunar\Storefront\Data\ProductOptionPermutation;

interface ProductManager
{
    public function getModelBySlug(string $slug): Product;

    /**
     * @return Collection<int, ProductOption>
     */
    public function getOptions(Product $product): Collection;

    /**
     * @param  Collection<int, ProductOption>|null  $options
     * @return Collection<int, ProductOptionPermutation>
     */
    public function getPermutations(Product $product, ?Collection $options = null): Collection;

    /**
     * @return Collection<int, ProductData>
     */
    public function getAssociations(Product $product, ProductAssociationType $association): Collection;
}
