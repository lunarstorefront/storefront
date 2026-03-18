<?php

namespace Lunar\Storefront\Http\Controllers\Checkout;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Lunar\Sales\Facades\CartSession;
use Lunar\Sales\Facades\Payments;
use Lunar\Stripe\Facades\Stripe;
use Lunar\Stripe\Models\StripePaymentIntent;
use Stripe\PaymentIntent;

class ProcessingController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if (! $intentId = $request->get('payment_intent')) {
            return redirect()->route('cart.show');
        }

        $intent = StripePaymentIntent::where('intent_id', $intentId)->firstOrFail();

        $paymentIntent = Stripe::fetchIntent($intent->intent_id);

        $cart = $intent->cart;
        $order = $intent->order ?: $cart->draftOrder;

        if ($paymentIntent->status == PaymentIntent::STATUS_SUCCEEDED && $order) {
            $paymentResponse = Payments::driver('stripe')->order($order)->withData([
                'payment_intent' => $paymentIntent->id,
            ])->authorize();

            if ($paymentResponse->success) {
                CartSession::forget(delete: false);
                session()->put('checkout.order', $order->reference);

                return redirect()->route('checkout.success');
            }
        }

        if ($cart->completedOrders()->exists()) {
            CartSession::forget(delete: false);
            session()->put('checkout.order', $cart->completedOrders()->latest()->first()->reference);

            return redirect()->route('checkout.success');
        }

        return Inertia::render('sales/checkout/Processing', []);
    }
}
