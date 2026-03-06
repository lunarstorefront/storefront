<?php

use Lunar\Storefront\Contracts\BrandManager;
use Lunar\Storefront\Contracts\CollectionManager;
use Lunar\Storefront\Contracts\PricingManager;
use Lunar\Storefront\Contracts\ProductManager;
use Lunar\Storefront\Contracts\PropManager;
use Lunar\Storefront\Contracts\SearchManager;
use Lunar\Storefront\Contracts\StorefrontManager;
use Lunar\Storefront\Contracts\VariantManager;

test('it registers prop manager as singleton', function () {
    $a = app(PropManager::class);
    $b = app(PropManager::class);

    expect($a)->toBe($b);
});

test('it registers storefront manager as singleton', function () {
    $a = app(StorefrontManager::class);
    $b = app(StorefrontManager::class);

    expect($a)->toBe($b);
});

test('it resolves product manager from container', function () {
    expect(app(ProductManager::class))
        ->toBeInstanceOf(\Lunar\Storefront\Managers\ProductManager::class);
});

test('it resolves variant manager from container', function () {
    expect(app(VariantManager::class))
        ->toBeInstanceOf(\Lunar\Storefront\Managers\VariantManager::class);
});

test('it resolves brand manager from container', function () {
    expect(app(BrandManager::class))
        ->toBeInstanceOf(\Lunar\Storefront\Managers\BrandManager::class);
});

test('it resolves collection manager from container', function () {
    expect(app(CollectionManager::class))
        ->toBeInstanceOf(\Lunar\Storefront\Managers\CollectionManager::class);
});

test('it resolves search manager from container', function () {
    expect(app(SearchManager::class))
        ->toBeInstanceOf(\Lunar\Storefront\Managers\SearchManager::class);
});

test('it resolves pricing manager from container', function () {
    expect(app(PricingManager::class))
        ->toBeInstanceOf(\Lunar\Storefront\Managers\PricingManager::class);
});

test('it merges storefront config', function () {
    expect(config('storefront'))->not->toBeNull();
});
