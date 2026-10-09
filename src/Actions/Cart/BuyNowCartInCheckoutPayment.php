<?php

namespace Lunar\Storefront\Actions\Cart;

use Lunar\Checkout\Models\CheckoutSession;
use Lunar\Checkout\States\CheckoutSession\PaymentProcessing;
use Lunar\Core\Models\Cart;
use Lunar\Storefront\Contracts\BuyNowCartGuard;

/**
 * The BuyNowCartGuard for lunarphp/checkout, bound when it is installed. Its
 * order is only created once the payment settles, from the cart the checkout
 * session is pinned to, so the cart must outlive a shopper who wanders off
 * while the payment is processing.
 */
class BuyNowCartInCheckoutPayment implements BuyNowCartGuard
{
    public function inUse(Cart $cart): bool
    {
        return CheckoutSession::query() // @phpstan-ignore class.notFound
            ->where('cart_reference', (string) $cart->id)
            ->where('status', PaymentProcessing::$name) // @phpstan-ignore class.notFound
            ->exists();
    }
}
