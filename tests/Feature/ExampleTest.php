<?php

use Lunar\Storefront\StorefrontServiceProvider;

test('service provider is registered', function () {
    expect(app()->getProvider(StorefrontServiceProvider::class))->not->toBeNull();
});
