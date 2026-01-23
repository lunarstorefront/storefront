<?php

namespace Lunar\Storefront\Contracts;

use Lunar\Models\Contracts\ProductVariant;

interface VariantManager
{
    public function getBySku(string $sku): ?ProductVariant;
}
