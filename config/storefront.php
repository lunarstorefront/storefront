<?php

use Lunar\Storefront\Install\Presets\UkPreset;

return [
    'key' => env('STOREFRONT_KEY'),

    'buy_now' => [
        /*
         * Route names (patterns allowed) that are part of the checkout. While
         * a Buy Now is in progress, a GET to any other route brings the
         * shopper's basket back (RestoreBasketAfterBuyNow). The checkout's
         * success page must be listed, or the basket comes back before that
         * page forgets the session's cart and is lost with it: add your own
         * success route here if the checkout does not return to
         * `checkout.success`.
         */
        'checkout_routes' => ['lunar.checkout.*', 'checkout.success', 'checkout.order-issue'],
    ],

    'install' => [
        // package => a class that exists when it is installed
        'requires' => [
            'lunarphp/table-rate-shipping' => 'Lunar\\Shipping\\ShippingServiceProvider',
            'lunarphp/panel' => 'Lunar\\Panel\\PanelServiceProvider',
            'lunarphp/checkout' => 'Lunar\\Checkout\\CheckoutServiceProvider',
            'lunarphp/lunar-cms' => 'Lunar\\Cms\\CmsServiceProvider',
            'neoncommerce/storefront-cache' => 'NeonCommerce\\StorefrontCache\\StorefrontCacheServiceProvider',
        ],
        'publish' => [
            // published only if missing
            'config' => ['panel-config'],
            // always --force
            'assets' => ['panel-all-assets', 'lunar-checkout-assets'],
        ],
        'search_command' => 'lunar:search:index',
    ],

    // Fallbacks for the storefront:install --admin-* options
    'install_admin' => [
        'email' => env('STOREFRONT_ADMIN_EMAIL'),
        'firstname' => env('STOREFRONT_ADMIN_FIRSTNAME'),
        'lastname' => env('STOREFRONT_ADMIN_LASTNAME'),
        'password' => env('STOREFRONT_ADMIN_PASSWORD'),
    ],

    'navigation' => [
        // Collection group handle the header menu is built from
        'group' => 'main',
        // Levels of collections to include
        'depth' => 2,
    ],

    'search' => [
        'per_page' => ['default' => 40, 'options' => [40, 80, 120]],
        'price_filter' => ['min_key' => 'price_min', 'max_key' => 'price_max', 'vat_key' => 'price_vat', 'vat_rate' => 20],
    ],

    'stock' => [
        // Stock at or below this (and above zero) reads as low stock
        'low_threshold' => 5,
        // Expose the unit count on the product page
        'show_quantity' => false,
        // Product attribute handle holding the backorder lead time in days
        'lead_time_attribute' => null,
        // Above this the cart limit is an allowance, not real stock
        'practical_limit' => 999,
    ],

    'presets' => [
        'uk' => UkPreset::class,
    ],
];
