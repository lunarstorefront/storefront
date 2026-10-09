<?php

namespace Lunar\Storefront;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Lunar\Checkout\Models\CheckoutSession;
use Lunar\Core\Contracts\Actions\Carts\AssociatesUser;
use Lunar\Core\Contracts\Actions\Customers\CreatesCustomer;
use Lunar\Core\Contracts\Actions\Customers\UpdatesCustomer;
use Lunar\Core\Contracts\LunarUser;
use Lunar\Core\Events\Orders\OrderPlaced;
use Lunar\Core\Models\Customer;
use Lunar\Storefront\Actions\Account\CreateCustomerWithGroups;
use Lunar\Storefront\Actions\Account\SyncCustomerGroups;
use Lunar\Storefront\Actions\Account\UpdateCustomerWithGroups;
use Lunar\Storefront\Actions\Cart\AssociateUserKeepingBuyNowApart;
use Lunar\Storefront\Actions\Cart\BuyNow;
use Lunar\Storefront\Actions\Cart\BuyNowCartInCheckoutPayment;
use Lunar\Storefront\Actions\Cart\BuyNowCartNotInUse;
use Lunar\Storefront\Console\ConfigureMeilisearchQuerySuggestions;
use Lunar\Storefront\Contracts\BrandManager;
use Lunar\Storefront\Contracts\BuyNowCartGuard;
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

        // Buy Now (Actions\Cart\BuyNow): signing in mid-checkout must not
        // merge the user's saved cart into a Buy Now cart, and signing in
        // later must not merge an unfinished Buy Now cart into the basket.
        // Inert until a Buy Now starts.
        $this->app->extend(AssociatesUser::class, fn (AssociatesUser $action, Application $app) => new AssociateUserKeepingBuyNowApart($action, $app->make(BuyNow::class)));

        // Which Buy Now carts may still become an order once the shopper has
        // left the checkout: with lunarphp/checkout, those whose payment is
        // still processing (the order is created when it settles).
        $this->app->bind(BuyNowCartGuard::class, fn () => class_exists(CheckoutSession::class)
            ? new BuyNowCartInCheckoutPayment
            : new BuyNowCartNotInUse);
    }

    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/storefront.php', 'storefront');

        Customer::saved(fn (Customer $customer) => $this->app->make(SyncCustomerGroups::class)->sync($customer));

        // Buy Now: a signed-in user's Buy Now carts left unfinished in an
        // earlier session would otherwise be Lunar's pick for their cart at
        // sign-in. On sign-out the parked basket goes the way Lunar sends the
        // session's cart, so it is not handed to whoever browses next.
        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof LunarUser) {
                $this->app->make(BuyNow::class)->forgetAbandoned($event->user);
            }
        });
        Event::listen(Logout::class, fn () => $this->app->make('session.store')->forget(BuyNow::PARKED_KEY));
        // Order lines keep the part number they were sold under.
        Event::listen(OrderPlaced::class, StampOrderLinePartNumbers::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ConfigureMeilisearchQuerySuggestions::class,
            ]);
        }
    }
}
