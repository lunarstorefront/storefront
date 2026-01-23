<?php

namespace Lunar\Storefront\Contracts;

use Illuminate\Support\Collection;
use Lunar\Models\Contracts\Product;

interface ProductManager
{
    public function getOptions(Product $product): Collection;

    public function getPermutations(Product $product, ?Collection $options = null): Collection;
}
