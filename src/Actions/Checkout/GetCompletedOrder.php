<?php

namespace Lunar\Storefront\Actions\Checkout;

use Illuminate\Contracts\Session\Session;
use Lunar\Core\Models\Order;

final class GetCompletedOrder
{
    /**
     * The order the checkout package handed over on its way out.
     *
     * `lunar.checkout.completed` in the session is the only proof this browser
     * placed the order, so the id is never taken from the request.
     *
     * @param  list<string>  $with
     */
    public function handle(Session $session, array $with = []): ?Order
    {
        $handover = $session->get('lunar.checkout.completed');

        if (! is_array($handover) || ! is_numeric($handover['order_reference'] ?? null)) {
            return null;
        }

        return Order::query()
            ->whereKey((int) $handover['order_reference'])
            ->whereNotNull('placed_at')
            ->with($with)
            ->first();
    }
}
