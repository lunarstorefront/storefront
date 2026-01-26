<?php

namespace Lunar\Storefront\Facades;

use Illuminate\Support\Facades\Facade;
use Lunar\Storefront\Contracts\StorefrontManager;

/**
 * @method static variants(): VariantManager;
 * @method static products(): ProductManager;
 * @method static collections(): CollectionManager;
 * @see StorefrontManager
 */
class Storefront extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return StorefrontManager::class;
    }
}
