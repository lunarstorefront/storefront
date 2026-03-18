<?php

namespace Lunar\Storefront\Contracts;

use Illuminate\Support\Collection;
use Lunar\Catalog\Models\Product;
use Lunar\Catalog\Models\ProductVariant;

interface VariantManager
{
    public function getBySku(string $sku): ?ProductVariant;

    public function encryptOptions(array $options): string;

    public function decryptOptions(?string $hash = null): array;

    public function getSelectedOptions(?string $hash = null): Collection;

    public function getProvidedVariant(Product $product, ?string $hash = null): ?ProductVariant;
}
