<?php

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
use Lunar\Storefront\Facades\Storefront;
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

    $this->variant = ProductVariant::factory()
        ->for(Product::factory()->for(ProductType::factory()))
        ->for(TaxClass::factory()->create(['default' => true]))
        ->create();

    foreach ([[null, 1000], [$this->trade->id, 800]] as [$customerGroupId, $amount]) {
        Price::factory()->create([
            'priceable_type' => $this->variant->getMorphClass(),
            'priceable_id' => $this->variant->id,
            'currency_id' => $currency->id,
            'customer_group_id' => $customerGroupId,
            'price' => $amount,
            'min_quantity' => 1,
        ]);
    }
});

test('a signed-in customer in a trade group is priced at the trade price', function () {
    $customer = Customer::factory()->create();
    $customer->customerGroups()->sync([$this->trade->id]);

    $user = User::query()->create(['name' => 'Sam Fitter', 'email' => 'sam@example.com', 'password' => 'secret']);
    $user->customers()->attach($customer);

    $this->actingAs($user);

    expect(Storefront::pricing()->getPricing($this->variant)->matched->price)->toBe(800);
});

test('a guest is priced at the default price', function () {
    expect(Storefront::pricing()->getPricing($this->variant)->matched->price)->toBe(1000);
});
