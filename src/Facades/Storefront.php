<?php

namespace Lunar\Storefront\Facades;

use Illuminate\Support\Facades\Facade;
use Lunar\Storefront\Contracts\CollectionManager;
use Lunar\Storefront\Contracts\PricingManager;
use Lunar\Storefront\Contracts\ProductManager;
use Lunar\Storefront\Contracts\SearchManager;
use Lunar\Storefront\Contracts\StorefrontManager;
use Lunar\Storefront\Contracts\VariantManager;

/**
 * @method static VariantManager variants()
 * @method static ProductManager products()
 * @method static CollectionManager collections()
 * @method static SearchManager search()
 * @method static PricingManager pricing()
 * @method static void setCurrency(string $code)
 * @method static void setLocale(string $locale)
 *
 * @see \Lunar\Storefront\Managers\StorefrontManager
 */
class Storefront extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return StorefrontManager::class;
    }
}
