<?php

namespace Lunar\Storefront\Contracts;

use Lunar\Core\Models\Cart;

/**
 * Tells BuyNow whether a Buy Now cart may still become an order, so it is
 * kept rather than thrown away when the shopper leaves the checkout or signs
 * in again. A cart with an order of any kind is always kept; this covers
 * checkouts that create no order until payment completes.
 *
 * Bound by default to a guard for lunarphp/checkout when it is installed (a
 * checkout session for the cart is processing a payment), and otherwise to
 * one that reports no cart in use. Bind your own for another checkout.
 */
interface BuyNowCartGuard
{
    public function inUse(Cart $cart): bool;
}
