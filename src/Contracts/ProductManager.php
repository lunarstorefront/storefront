<?php

namespace Lunar\Storefront\Contracts;

use Illuminate\Support\Collection;
use Lunar\Base\Enums\ProductAssociation;
use Lunar\Models\Contracts\Product;

interface ProductManager
{
    public function getModelBySlug(string $slug): Product;

    public function getOptions(Product $product): Collection;

    public function getPermutations(Product $product, ?Collection $options = null): Collection;

    public function getAssociations(Product $product, ProductAssociation $association): Collection;

    public function getShowPageProps(Product $product, ?string $optionHash, int $quantity = 1): array;
}
