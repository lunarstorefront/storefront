<?php

namespace Lunar\Storefront\Contracts;

use Illuminate\Support\Collection;
use Lunar\Core\Enums\ProductAssociation;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductOption;
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
     * @param  bool  $withPrices  Carry each permutation's variant price, at one pricing lookup each
     * @return Collection<int, ProductOptionPermutation>
     */
    public function getPermutations(Product $product, ?Collection $options = null, bool $withPrices = false): Collection;

    /**
     * @return Collection<int, ProductData>
     */
    public function getAssociations(Product $product, ProductAssociation $association): Collection;
}
