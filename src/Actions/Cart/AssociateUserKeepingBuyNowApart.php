<?php

namespace Lunar\Storefront\Actions\Cart;

use Lunar\Core\Contracts\Actions\Carts\AssociatesUser;
use Lunar\Core\Contracts\LunarUser;
use Lunar\Core\Models\Cart;

/**
 * Wraps whichever associate-user action is bound so that signing in during a
 * Buy Now checkout leaves the Buy Now cart holding only its item. Lunar's
 * `merge` policy would pull the user's saved cart into it, and `override`
 * would retire that saved cart; instead the Buy Now cart is just given the
 * user and their customer (so their prices apply), and the saved cart is left
 * alone. Every other cart goes through the wrapped action.
 */
class AssociateUserKeepingBuyNowApart implements AssociatesUser
{
    public function __construct(private readonly AssociatesUser $inner) {}

    public function execute(Cart $cart, LunarUser $user, string $policy = 'merge'): void
    {
        if (! BuyNow::isBuyNowCart($cart)) {
            $this->inner->execute($cart, $user, $policy);

            return;
        }

        $cart->update([
            // The user's key, through the contract (LunarUser has no getKey()).
            'user_id' => $user->carts()->getParentKey(),
            'customer_id' => $user->latestCustomer()?->getKey(),
        ]);
    }
}
