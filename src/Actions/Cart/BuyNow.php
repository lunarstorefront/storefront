<?php

namespace Lunar\Storefront\Actions\Cart;

use Illuminate\Contracts\Session\Session;
use Lunar\Core\Facades\CartSession;
use Lunar\Core\Models\Cart;
use Lunar\Core\Models\ProductVariant;

/**
 * Buy Now: check out one item without touching the shopper's basket.
 *
 * Lunar's checkout runs on the session's cart, so for the length of a Buy Now
 * checkout the session's cart is a separate one holding just that item, and
 * the basket is parked (its id kept in the session). restore() swaps the
 * basket back and throws the Buy Now cart away, unless an order was placed
 * from it; the RestoreBasketAfterBuyNow middleware calls it on the shopper's
 * first page outside the checkout.
 *
 * Opt-in: nothing here runs until the host calls start() (typically from
 * its own Buy Now route, which then opens the checkout) and appends the
 * middleware to its `web` group.
 */
class BuyNow
{
    /** Session key holding the parked basket: ['cart_id' => ?int]. */
    public const PARKED_KEY = 'lunar_storefront_buy_now';

    /** Cart meta flag marking a Buy Now cart. */
    public const META_KEY = 'buy_now';

    public function __construct(private readonly Session $session) {}

    /**
     * Park the basket and make a cart holding only $quantity of $variant the
     * session's cart. Stock and quantity rules are the caller's to validate,
     * as for any add to cart: `InStock` with no cart, since the Buy Now cart
     * starts empty.
     */
    public function start(ProductVariant $variant, int $quantity): Cart
    {
        $current = CartSession::current(calculate: false);

        if ($current !== null && self::isBuyNowCart($current)) {
            // A Buy Now left unfinished: the basket parked then is still the
            // one to come back to.
            $basket = null;
            $this->discard($current);
        } else {
            $basket = $current;
            $this->session->put(self::PARKED_KEY, ['cart_id' => $basket?->id]);
        }

        CartSession::forget(delete: false);

        if ($basket !== null) {
            // Same shopper and pricing context as the basket. Created here
            // rather than by CartSession: with no cart in the session it
            // falls back to a signed-in user's latest cart, the basket.
            CartSession::use(Cart::create([
                ...$basket->only(['currency_id', 'channel_id', 'region_id', 'user_id', 'customer_id']),
                'meta' => [self::META_KEY => true],
            ]));
        }

        // Proxied to the session's cart, which it creates when there is none.
        CartSession::add($variant, $quantity); // @phpstan-ignore staticMethod.notFound

        $cart = CartSession::current(calculate: false);

        if (! self::isBuyNowCart($cart)) {
            $cart->update(['meta' => [...(array) $cart->getAttribute('meta'), self::META_KEY => true]]);
        }

        return $cart->refresh()->calculate();
    }

    /**
     * Whether a Buy Now is in progress for this session.
     */
    public function active(): bool
    {
        return $this->session->has(self::PARKED_KEY);
    }

    /**
     * Swap the parked basket back in as the session's cart and discard the
     * Buy Now cart. A guest basket parked before signing in during checkout
     * is associated with the user now, under Lunar's auth policy, as signing
     * in would have done with it in the session.
     */
    public function restore(): void
    {
        if (! $this->active()) {
            return;
        }

        $parked = (array) $this->session->pull(self::PARKED_KEY);
        $current = CartSession::current(calculate: false);

        if ($current !== null && self::isBuyNowCart($current)) {
            $this->discard($current);
        }

        CartSession::forget(delete: false);

        $basket = isset($parked['cart_id']) ? Cart::query()->find($parked['cart_id']) : null;

        if ($basket === null || $basket->hasCompletedOrders()) {
            return;
        }

        $user = auth()->user();

        if ($user !== null && $basket->user_id === null && is_lunar_user($user)) {
            CartSession::associate($basket, $user, config('lunar.cart.auth_policy'));

            return;
        }

        CartSession::use($basket);
    }

    public static function isBuyNowCart(?Cart $cart): bool
    {
        return (bool) ($cart?->getAttribute('meta')[self::META_KEY] ?? false);
    }

    /**
     * Delete a Buy Now cart nobody ordered from: left behind, a signed-in
     * user's next session would pick it up as their latest cart.
     */
    protected function discard(Cart $cart): void
    {
        if (! $cart->hasCompletedOrders()) {
            $cart->delete();
        }
    }
}
