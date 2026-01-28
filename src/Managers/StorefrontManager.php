<?php

namespace Lunar\Storefront\Managers;

use Lunar\Storefront\Actions\SetCurrency;
use Lunar\Storefront\Actions\SetLocale;
use Lunar\Storefront\Contracts\VariantManager;
use Lunar\Storefront\Contracts\ProductManager;
use Lunar\Storefront\Contracts\CollectionManager;
use Lunar\Storefront\Contracts\SearchManager;

class StorefrontManager implements \Lunar\Storefront\Contracts\StorefrontManager
{
    public function variants(): VariantManager
    {
        return app(VariantManager::class);
    }

    public function products(): ProductManager
    {
        return app(ProductManager::class);
    }

    public function collections(): CollectionManager
    {
        return app(CollectionManager::class);
    }

    public function search(): SearchManager
    {
        return app(SearchManager::class);
    }

    public function pricing(): PricingManager
    {
        return app(PricingManager::class);
    }

    public function setCurrency(string $code): void
    {
        (new SetCurrency)->set($code);
    }

    public function setLocale(string $locale): void
    {
        (new SetLocale)->set($locale);
    }
}
