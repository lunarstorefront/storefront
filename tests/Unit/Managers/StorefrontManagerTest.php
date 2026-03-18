<?php

use Illuminate\Support\Facades\Session;
use Lunar\Kernel\Models\Channel;
use Lunar\Kernel\Models\Currency;
use Lunar\Kernel\Models\CustomerGroup;
use Lunar\Kernel\Models\Language;
use Lunar\Kernel\Models\Region;
use Lunar\Sales\Facades\CartSession;
use Lunar\Storefront\Contracts\BrandManager;
use Lunar\Storefront\Contracts\CollectionManager;
use Lunar\Storefront\Contracts\PricingManager;
use Lunar\Storefront\Contracts\ProductManager;
use Lunar\Storefront\Contracts\SearchManager;
use Lunar\Storefront\Contracts\VariantManager;
use Lunar\Storefront\Managers\StorefrontManager;

beforeEach(function () {
    $this->manager = new StorefrontManager;
});

test('it returns variant manager instance', function () {
    $variants = $this->manager->variants();

    expect($variants)->toBeInstanceOf(VariantManager::class);
});

test('it returns product manager instance', function () {
    $products = $this->manager->products();

    expect($products)->toBeInstanceOf(ProductManager::class);
});

test('it returns collection manager instance', function () {
    $collections = $this->manager->collections();

    expect($collections)->toBeInstanceOf(CollectionManager::class);
});

test('it returns brand manager instance', function () {
    $brands = $this->manager->brands();

    expect($brands)->toBeInstanceOf(BrandManager::class);
});

test('it returns search manager instance', function () {
    $search = $this->manager->search();

    expect($search)->toBeInstanceOf(SearchManager::class);
});

test('it returns pricing manager instance', function () {
    $pricing = $this->manager->pricing();

    expect($pricing)->toBeInstanceOf(PricingManager::class);
});

test('it sets locale in session', function () {
    $this->manager->setLocale('fr');

    expect(Session::get('locale'))->toBe('fr');
});

test('it can set currency', function () {
    $language = Language::factory()->create(['default' => true]);

    $usd = Currency::factory()->create([
        'code' => 'USD',
        'default' => true,
    ]);

    $currency = Currency::factory()->create([
        'code' => 'EUR',
        'default' => false,
    ]);

    $channel = Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);

    Region::factory()->create([
        'default' => true,
        'channel_id' => $channel->id,
        'currency_id' => $usd->id,
        'language_id' => $language->id,
    ]);

    $this->manager->setCurrency('EUR');

    expect(CartSession::getCurrency()->code)->toBe('EUR');
})->skip('V2: SetCurrency needs rework — CartSession::setCurrency only affects active carts, not the StorefrontContext');
