<?php

test('service provider is registered', function () {
    expect(app()->getProvider(\Lunar\Storefront\StorefrontServiceProvider::class))->not->toBeNull();
});
