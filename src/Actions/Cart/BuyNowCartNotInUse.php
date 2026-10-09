<?php

namespace Lunar\Storefront\Actions\Cart;

use Lunar\Core\Models\Cart;
use Lunar\Storefront\Contracts\BuyNowCartGuard;

/** The default BuyNowCartGuard: only a cart's orders keep it. */
class BuyNowCartNotInUse implements BuyNowCartGuard
{
    public function inUse(Cart $cart): bool
    {
        return false;
    }
}
