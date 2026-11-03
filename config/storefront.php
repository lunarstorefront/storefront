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

    'presets' => [
        'uk' => UkPreset::class,
    ],
];
