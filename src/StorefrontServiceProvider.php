<?php

namespace Lunar\Storefront;

use Illuminate\Support\ServiceProvider;
use Lunar\Storefront\Console\ConfigureMeilisearchQuerySuggestions;
use Lunar\Storefront\Contracts\BrandManager;
use Lunar\Storefront\Contracts\CollectionManager;
use Lunar\Storefront\Contracts\PricingManager;
use Lunar\Storefront\Contracts\ProductManager;
use Lunar\Storefront\Contracts\PropManager;
use Lunar\Storefront\Contracts\SearchManager;
use Lunar\Storefront\Contracts\StorefrontManager;
use Lunar\Storefront\Contracts\VariantManager;

class StorefrontServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PropManager::class, fn () => new \Lunar\Storefront\Managers\PropManager);
        $this->app->singleton(StorefrontManager::class, fn () => new \Lunar\Storefront\Managers\StorefrontManager);
        $this->app->bind(ProductManager::class, fn () => new \Lunar\Storefront\Managers\ProductManager);
        $this->app->bind(VariantManager::class, fn () => new \Lunar\Storefront\Managers\VariantManager);
        $this->app->bind(BrandManager::class, fn () => new \Lunar\Storefront\Managers\BrandManager);
        $this->app->bind(CollectionManager::class, fn () => new \Lunar\Storefront\Managers\CollectionManager);
        $this->app->bind(SearchManager::class, fn () => new \Lunar\Storefront\Managers\SearchManager);
        $this->app->bind(PricingManager::class, fn () => new \Lunar\Storefront\Managers\PricingManager);
    }

    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/storefront.php', 'storefront');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ConfigureMeilisearchQuerySuggestions::class,
            ]);
        }
    }
}
