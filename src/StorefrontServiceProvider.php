<?php

namespace Lunar\Storefront;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Lunar\Core\Contracts\Actions\Customers\CreatesCustomer;
use Lunar\Core\Contracts\Actions\Customers\UpdatesCustomer;
use Lunar\Core\Events\Orders\OrderPlaced;
use Lunar\Core\Models\Customer;
use Lunar\Storefront\Actions\Account\CreateCustomerWithGroups;
use Lunar\Storefront\Actions\Account\SyncCustomerGroups;
use Lunar\Storefront\Actions\Account\UpdateCustomerWithGroups;
use Lunar\Storefront\Console\ConfigureMeilisearchQuerySuggestions;
use Lunar\Storefront\Contracts\BrandManager;
use Lunar\Storefront\Contracts\CollectionManager;
use Lunar\Storefront\Contracts\PricingManager;
use Lunar\Storefront\Contracts\ProductManager;
use Lunar\Storefront\Contracts\PropManager;
use Lunar\Storefront\Contracts\SearchManager;
use Lunar\Storefront\Contracts\StorefrontManager;
use Lunar\Storefront\Contracts\VariantManager;
use Lunar\Storefront\Listeners\StampOrderLinePartNumbers;

class StorefrontServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PropManager::class, fn () => new Managers\PropManager);
        $this->app->singleton(StorefrontManager::class, fn () => new Managers\StorefrontManager);
        $this->app->bind(ProductManager::class, fn () => new Managers\ProductManager);
        $this->app->bind(VariantManager::class, fn () => new Managers\VariantManager);
        $this->app->bind(BrandManager::class, fn () => new Managers\BrandManager);
        $this->app->bind(CollectionManager::class, fn () => new Managers\CollectionManager);
        $this->app->bind(SearchManager::class, fn () => new Managers\SearchManager);
        $this->app->bind(PricingManager::class, fn () => new Managers\PricingManager);

        // Customer group rules (Contracts\CustomerGroupResolver). Lunar's
        // customer actions sync the groups they are given after saving, so
        // they are wrapped to re-derive groups afterwards. Both wrappers and
        // the save hook in boot() do nothing until a resolver is bound.
        $this->app->extend(CreatesCustomer::class, fn (CreatesCustomer $action, Application $app) => new CreateCustomerWithGroups($action, $app->make(SyncCustomerGroups::class)));
        $this->app->extend(UpdatesCustomer::class, fn (UpdatesCustomer $action, Application $app) => new UpdateCustomerWithGroups($action, $app->make(SyncCustomerGroups::class)));
    }

    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/storefront.php', 'storefront');

        Customer::saved(fn (Customer $customer) => $this->app->make(SyncCustomerGroups::class)->sync($customer));

        // Order lines keep the part number they were sold under.
        Event::listen(OrderPlaced::class, StampOrderLinePartNumbers::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ConfigureMeilisearchQuerySuggestions::class,
            ]);
        }
    }
}
