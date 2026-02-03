<?php

namespace Lunar\Storefront;

use Illuminate\Support\ServiceProvider;
use Lunar\Facades\StorefrontSession;
use Lunar\Models\Contracts\Product;
use Lunar\Storefront\Actions\Catalog\GetProductOptionPermutations;
use Lunar\Storefront\Actions\Catalog\GetProductOptions;
use Lunar\Storefront\Console\ConfigureMeilisearchQuerySuggestions;
use Lunar\Storefront\Contracts\CollectionManager;
use Lunar\Storefront\Contracts\PricingManager;
use Lunar\Storefront\Contracts\ProductManager;
use Lunar\Storefront\Contracts\PropManager;
use Lunar\Storefront\Contracts\SearchManager;
use Lunar\Storefront\Contracts\StorefrontManager;
use Lunar\Storefront\Contracts\VariantManager;
use Lunar\Storefront\Data\Address;
use Lunar\Storefront\Data\Country;
use Lunar\Storefront\Data\Currency;
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
        $this->app->bind(SearchManager::class, fn () => new \Lunar\Storefront\Managers\SearchManager());
        $this->app->bind(PricingManager::class, fn () => new \Lunar\Storefront\Managers\PricingManager());
    }

    public function boot()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/storefront.php', 'storefront');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ConfigureMeilisearchQuerySuggestions::class,
            ]);
        }

        Props::add([
            new PropData(
                page: 'global',
                key: 'currencies',
                callback: fn () => Currency::collect(
                    \Lunar\Models\Currency::get()
                ),
            ),
            new PropData(
                page: 'global',
                key: 'currentCurrency',
                callback: fn () => Currency::from(
                    StorefrontSession::getCurrency()
                ),
            ),
            new PropData(
                page: 'products.show',
                key: 'permutations',
                callback: function (Product $product) {
                    $productOptions = (new GetProductOptions)->get($product);
                    return (new GetProductOptionPermutations)->get($productOptions, $product);
                },
            ),
            new PropData(
                page: 'account.addresses.index',
                key: 'addresses',
                callback: function () {
                    $user = auth()->user();

                    $customer = $user->latestCustomer();

                    return Address::collect($customer->addresses);
                }
            ),
            new PropData(
                page: 'account.addresses.index',
                key: 'countries',
                callback: function () {
                    return Country::collect(
                        \Lunar\Models\Country::get()
                    );
                }
            )
        ]);
    }
}


