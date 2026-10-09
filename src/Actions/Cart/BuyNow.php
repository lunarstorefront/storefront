<?php

namespace Lunar\Storefront\Actions\Cart;

use Illuminate\Contracts\Session\Session;
use Lunar\Core\Contracts\Actions\Storefront\ResolvesStorefrontContext;
use Lunar\Core\Contracts\LunarUser;
use Lunar\Core\Facades\CartSession;
use Lunar\Core\Models\Cart;
use Lunar\Core\Models\ProductVariant;
use Lunar\Storefront\Contracts\BuyNowCartGuard;
use Throwable;

/**
 * Buy Now: check out one item without touching the shopper's basket.
 *
 * Lunar's checkout runs on the session's cart, so for the length of a Buy Now
 * checkout the session's cart is a separate one holding just that item, and
 * the basket is parked (its id kept in the session). restore() swaps the
 * basket back and throws the Buy Now cart away, unless it may still become
 * an order; the RestoreBasketAfterBuyNow middleware calls it on the shopper's
 * first page outside the checkout.
 *
 * start() and restore() read the session's cart id directly, and start()
 * creates the Buy Now cart itself: CartSession::current() puts a new cart in
 * the session once an order is placed, and with no cart in the session it
 * falls back to a signed-in user's latest cart.
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

    public function __construct(
        private readonly Session $session,
        private readonly BuyNowCartGuard $guard,
    ) {}

    /**
     * Park the basket and make a cart holding only $quantity of $variant the
     * session's cart. Lunar's add-to-cart validators run as for any add to
     * cart, against the empty Buy Now cart; when they throw, the session is
     * left as it was.
     */
    public function start(ProductVariant $variant, int $quantity): Cart
    {
        $sessionCart = $this->sessionCart();
        $active = $this->active();

        // A Buy Now already in progress (left unfinished, or finished but not
        // yet left): the basket parked when it began is still the one to
        // come back to.
        $basket = $active ? $this->parkedBasket() : $this->basketFor($sessionCart);

        $cart = Cart::create([
            ...$this->contextFrom($basket),
            'meta' => [self::META_KEY => true],
        ]);

        try {
            $cart->add($variant, $quantity);
        } catch (Throwable $e) {
            $cart->forceDelete();

            throw $e;
        }

        if (! $active) {
            $this->session->put(self::PARKED_KEY, ['cart_id' => $basket?->id]);
        }

        if ($sessionCart !== null && self::isBuyNowCart($sessionCart)) {
            $this->discard($sessionCart);
        }

        CartSession::forget(delete: false);
        CartSession::use($cart);

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
     * in would have done with it in the session. A basket belonging to
     * someone other than the current visitor is not brought back.
     */
    public function restore(): void
    {
        if (! $this->active()) {
            return;
        }

        $parked = (array) $this->session->pull(self::PARKED_KEY);
        $current = $this->sessionCart();

        if ($current !== null && self::isBuyNowCart($current)) {
            $this->discard($current);
        }

        CartSession::forget(delete: false);

        $basket = isset($parked['cart_id']) ? Cart::query()->find($parked['cart_id']) : null;

        if ($basket === null || $basket->hasCompletedOrders()) {
            return;
        }

        $user = auth()->user();

        if ($basket->user_id !== null && (string) $basket->user_id !== (string) $user?->getAuthIdentifier()) {
            return;
        }

        if ($user !== null && $basket->user_id === null && is_lunar_user($user)) {
            CartSession::associate($basket, $user, config('lunar.cart.auth_policy'));

            return;
        }

        CartSession::use($basket);
    }

    /**
     * Retire a signed-in user's Buy Now carts left unfinished in an earlier
     * session: left behind, Lunar would pick one up as their latest cart. The
     * cart of a Buy Now in progress in this session is kept, and if the
     * session's cart was one of them it is replaced by the user's basket.
     */
    public function forgetAbandoned(LunarUser $user): void
    {
        $sessionCartId = $this->session->get(CartSession::getSessionKey());

        $abandoned = Cart::query()
            // The user's key, through the contract (LunarUser has no getKey()).
            ->where('user_id', $user->carts()->getParentKey())
            ->where('meta->'.self::META_KEY, true)
            ->whereDoesntHave('orders')
            ->when($this->active() && $sessionCartId, fn ($query) => $query->whereKeyNot($sessionCartId))
            ->get()
            ->reject(fn (Cart $cart) => $this->guard->inUse($cart));

        $abandoned->each(fn (Cart $cart) => $cart->delete());

        if ($abandoned->contains('id', $sessionCartId)) {
            CartSession::forget(delete: false);
            CartSession::current(calculate: false);
        }
    }

    public static function isBuyNowCart(?Cart $cart): bool
    {
        return (bool) ($cart?->getAttribute('meta')[self::META_KEY] ?? false);
    }

    /**
     * Delete a Buy Now cart that will not become an order: left behind, a
     * signed-in user's next session would pick it up as their latest cart.
     * A cart with any order (a draft may still be completed) or one the
     * guard reports in use, such as a payment still processing, is kept.
     */
    protected function discard(Cart $cart): void
    {
        if ($cart->orders()->exists() || $this->guard->inUse($cart)) {
            return;
        }

        $cart->delete();
    }

    protected function sessionCart(): ?Cart
    {
        $id = $this->session->get(CartSession::getSessionKey());

        return $id ? Cart::query()->find($id) : null;
    }

    protected function parkedBasket(): ?Cart
    {
        $id = ((array) $this->session->get(self::PARKED_KEY))['cart_id'] ?? null;

        return $id ? Cart::query()->find($id) : null;
    }

    /**
     * The basket to park: the session's cart, or with none a signed-in
     * user's latest cart, as Lunar would resolve it.
     */
    protected function basketFor(?Cart $sessionCart): ?Cart
    {
        $cart = $sessionCart;
        $user = auth()->user();

        if ($cart === null && $user !== null) {
            $cart = Cart::query()->where('user_id', $user->getAuthIdentifier())->unmerged()->active()->latest('id')->first();
        }

        return $cart === null || $cart->hasCompletedOrders() || self::isBuyNowCart($cart) ? null : $cart;
    }

    /**
     * The Buy Now cart's shopper and pricing context: the basket's, or with
     * no basket what CartSessionManager::createNewCart() would give a new
     * cart.
     *
     * @return array<string, mixed>
     */
    protected function contextFrom(?Cart $basket): array
    {
        if ($basket !== null) {
            return $basket->only(['currency_id', 'channel_id', 'region_id', 'user_id', 'customer_id']);
        }

        $user = auth()->user();
        $customer = $user instanceof LunarUser ? $user->latestCustomer() : null;

        $context = app(ResolvesStorefrontContext::class)->execute(
            channel: CartSession::getChannel(),
            currency: CartSession::getCurrency(),
            customer: $customer,
        );

        return [
            'currency_id' => $context->currency->id,
            'channel_id' => $context->channel->id,
            'region_id' => $context->region?->id,
            'user_id' => $user?->getAuthIdentifier(),
            'customer_id' => $customer?->id,
        ];
    }
}
