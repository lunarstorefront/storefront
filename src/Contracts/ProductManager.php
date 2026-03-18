<?php

namespace Lunar\Storefront\Contracts;

use Illuminate\Support\Collection;
use Lunar\Catalog\Enums\ProductAssociationType;
use Lunar\Catalog\Models\Product;

interface ProductManager
{
    public function getModelBySlug(string $slug): Product;

    public function getOptions(Product $product): Collection;

    public function getPermutations(Product $product, ?Collection $options = null): Collection;

    public function getAssociations(Product $product, ProductAssociationType $association): Collection;
}
