<?php

namespace Lunar\Storefront\Contracts;

interface StorefrontManager
{
    public function variants(): VariantManager;
    public function products(): ProductManager;
}
