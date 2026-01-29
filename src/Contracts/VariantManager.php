<?php

namespace Lunar\Storefront\Contracts;

use Lunar\Models\Contracts\Product;
use Lunar\Models\Contracts\ProductVariant;

interface VariantManager
{
    public function getBySku(string $sku): ?ProductVariant;

    public function encryptOptions(array $options): string;

    public function decryptOptions(?string $hash = null): array;

    public function getProvidedVariant(Product $product, ?string $hash = null): ?ProductVariant;
}
