<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;
use Lunar\Core\Facades\Pricing;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Customer;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Price;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductType;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\Region;
use Lunar\Core\Models\TaxClass;
use Lunar\Storefront\Http\Middleware\UseStorefrontPricingContext;
use Lunar\Storefront\Tests\Stubs\User;

beforeEach(function () {
    $language = Language::factory()->create(['default' => true]);
    $currency = Currency::factory()->create(['default' => true]);
    $channel = Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true, 'handle' => 'retail']);
    $this->trade = CustomerGroup::factory()->create(['default' => false, 'handle' => 'trade']);

    Region::factory()->create([
        'default' => true,
        'channel_id' => $channel->id,
        'currency_id' => $currency->id,
        'language_id' => $language->id,
    ]);

    $variant = ProductVariant::factory()
        ->for(Product::factory()->for(ProductType::factory()))
        ->for(TaxClass::factory()->create(['default' => true]))
        ->create();

    foreach ([[null, 1000], [$this->trade->id, 800]] as [$customerGroupId, $amount]) {
        Price::factory()->create([
            'priceable_type' => $variant->getMorphClass(),
            'priceable_id' => $variant->id,
            'currency_id' => $currency->id,
            'customer_group_id' => $customerGroupId,
            'price' => $amount,
            'min_quantity' => 1,
        ]);
    }

    // Core pricing given no context, as Lunar's own code and host apps call it.
    Route::middleware('web')->get('price-check', fn () => Pricing::for($variant)->get()->matched->price);
});

test('the web group defaults pricing to the storefront context', function () {
    expect(app(Kernel::class)->getMiddlewareGroups()['web'])->toContain(UseStorefrontPricingContext::class);
});

test('a web request prices a signed-in trade customer at the trade price', function () {
    $customer = Customer::factory()->create();
    $customer->customerGroups()->sync([$this->trade->id]);

    $user = User::query()->create(['name' => 'Sam Fitter', 'email' => 'sam@example.com', 'password' => 'secret']);
    $user->customers()->attach($customer);

    $this->actingAs($user)->get('price-check')->assertOk()->assertContent('800');
});

test('a web request prices a guest at the default price', function () {
    $this->get('price-check')->assertOk()->assertContent('1000');
});
