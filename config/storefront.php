<?php

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
];
