<?php

namespace Lunar\Storefront\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Lunar\Core\Contracts\DefaultStorefrontContext;
use Lunar\Core\DataObjects\StorefrontContext;
use Lunar\Core\Facades\StorefrontSession;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes the storefront session's context (currency, channel, the signed-in
 * customer and their groups) the default for the request, so Lunar prices
 * anything given no context the way the basket will charge it.
 *
 * The same as StorefrontSession::useAsDefaultContext(), except the session
 * is only built when something prices: a request that never prices does not
 * pay for it, and does not fail on a store with no default channel yet. The
 * context resolves on use, so a sign-in or currency switch is seen.
 *
 * The service provider appends this to the `web` group.
 */
class UseStorefrontPricingContext
{
    public function handle(Request $request, Closure $next): Response
    {
        app(DefaultStorefrontContext::class)->resolveUsing(
            fn (): StorefrontContext => StorefrontSession::context()
        );

        return $next($request);
    }
}
