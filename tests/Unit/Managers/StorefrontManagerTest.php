<?php

use Illuminate\Support\Facades\Session;
use Lunar\Storefront\Contracts\BrandManager;
use Lunar\Storefront\Contracts\CollectionManager;
use Lunar\Storefront\Contracts\PricingManager;
use Lunar\Storefront\Contracts\ProductManager;
use Lunar\Storefront\Contracts\SearchManager;
use Lunar\Storefront\Contracts\VariantManager;
use Lunar\Storefront\Managers\StorefrontManager;

beforeEach(function () {
    $this->manager = new StorefrontManager();
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
    \Lunar\Kernel\Models\Currency::factory()->create([
        'code' => 'USD',
        'default' => true,
    ]);

    $currency = \Lunar\Kernel\Models\Currency::factory()->create([
        'code' => 'EUR',
        'default' => false,
    ]);

    \Lunar\Kernel\Models\Channel::factory()->create(['default' => true]);
    \Lunar\Kernel\Models\CustomerGroup::factory()->create(['default' => true]);

    $this->manager->setCurrency('EUR');

    expect(\Lunar\Sales\Facades\CartSession::getCurrency()->code)->toBe('EUR');
});
