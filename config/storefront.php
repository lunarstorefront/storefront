<?php

return [
    'key' => env('STOREFRONT_KEY'),

    'buy_now' => [
        /*
         * Route names (patterns allowed) that are part of the checkout. While
         * a Buy Now is in progress, a GET to any other route brings the
         * shopper's basket back (RestoreBasketAfterBuyNow).
         */
        'checkout_routes' => ['lunar.checkout.*'],
    ],
];
