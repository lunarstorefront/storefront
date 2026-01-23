<?php

namespace Lunar\Storefront\Managers;

use Lunar\Storefront\Contracts\VariantManager as VariantManagerContract;
use Lunar\Storefront\Contracts\ProductManager as ProductManagerContract;

class StorefrontManager implements \Lunar\Storefront\Contracts\StorefrontManager
{
    public function variants(): VariantManager
    {
        return app(VariantManagerContract::class);
    }

    public function products(): ProductManager
    {
        return app(ProductManagerContract::class);
    }
}
