<?php

namespace Lunar\Storefront;

use Illuminate\Support\ServiceProvider;
use Lunar\Models\Contracts\Product;
use Lunar\Storefront\Actions\Catalog\GetProductOptionPermutations;
use Lunar\Storefront\Actions\Catalog\GetProductOptions;
use Lunar\Storefront\Console\StorefrontKeyGenerateCommand;
use Lunar\Storefront\Contracts\CollectionManager;
use Lunar\Storefront\Contracts\ProductManager;
use Lunar\Storefront\Contracts\PropManager;
use Lunar\Storefront\Contracts\StorefrontManager;
use Lunar\Storefront\Contracts\VariantManager;
use Lunar\Storefront\Facades\Props;

class StorefrontServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(PropManager::class , fn () => new \Lunar\Storefront\Managers\PropManager());
        $this->app->singleton(StorefrontManager::class , fn () => new \Lunar\Storefront\Managers\StorefrontManager());
        $this->app->bind(ProductManager::class, fn () => new \Lunar\Storefront\Managers\ProductManager());
        $this->app->bind(VariantManager::class, fn () => new \Lunar\Storefront\Managers\VariantManager());
        $this->app->bind(CollectionManager::class, fn () => new \Lunar\Storefront\Managers\CollectionManager());
    }

    public function boot()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/storefront.php', 'storefront');

        if ($this->app->runningInConsole()) {
            $this->commands([
                StorefrontKeyGenerateCommand::class,
            ]);
        }

        Props::add(
            new PropData(
                page: 'products.show',
                key: 'permutations',
                callback: function (Product $product) {
                    $productOptions = (new GetProductOptions)->get($product);
                    return (new GetProductOptionPermutations)->get($productOptions, $product);
                },
            )
        );
    }
}


