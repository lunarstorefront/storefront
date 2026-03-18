<?php

namespace Lunar\Storefront\Http\Controllers\Checkout;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Lunar\Sales\Facades\CartSession;
use Lunar\Sales\Models\Order;
use Lunar\Storefront\Data\Order as OrderData;

class SuccessController extends Controller
{
    public function __invoke(): Response
    {
        $reference = session('checkout.order');

        $order = Order::where('reference', $reference)
            ->with(['billingAddress', 'shippingAddress', 'physicalLines'])
            ->first();

        abort_unless($order && $order->placed_at, 404);

        CartSession::forget(delete: false);

        return Inertia::render('sales/checkout/Success', [
            'order' => OrderData::from($order),
        ]);
    }
}
