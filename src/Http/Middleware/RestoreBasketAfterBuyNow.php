<?php

namespace Lunar\Storefront\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Lunar\Storefront\Actions\Cart\BuyNow;
use Symfony\Component\HttpFoundation\Response;

/**
 * Brings the shopper's basket back once they leave a Buy Now checkout, paid
 * or not: on the first GET outside the routes named in
 * `storefront.buy_now.checkout_routes`. Only GETs count, because the
 * checkout's own sign-in posts to the host's login route mid-checkout.
 *
 * Append to the `web` group to opt in, alongside the `buy-now` route group.
 */
class RestoreBasketAfterBuyNow
{
    public function __construct(private readonly BuyNow $buyNow) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET')
            && $request->hasSession()
            && $this->buyNow->active()
            && ! $request->routeIs(...(array) config('storefront.buy_now.checkout_routes', []))
        ) {
            $this->buyNow->restore();
        }

        return $next($request);
    }
}
