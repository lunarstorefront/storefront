<?php

namespace Lunar\Storefront\Http\Controllers\Checkout;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Lunar\Sales\Facades\CartSession;
use Lunar\Sales\Facades\Payments;
use Lunar\Stripe\Facades\Stripe;
use Stripe\PaymentIntent;

class CreateDraftOrderController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $cart = CartSession::current();

        if (! $cart) {
            abort(404);
        }

        if (! $cart->canCreateOrder()) {
            return $this->handleExistingIntent($cart);
        }

        $intent = $cart->paymentIntents->first();

        $order = $cart->createOrder();

        if ($intent) {
            $intent->update([
                'order_id' => $order->id,
            ]);
        }

        try {
            Stripe::updateIntentById(
                Stripe::getCartIntentId($cart), [
                    'amount' => $order->total,
                    'metadata' => [
                        'order_id' => $order->id,
                    ],
                ]);
        } catch (\Exception $e) {
            return $this->handleAlreadyProcessedIntent($cart, $order);
        }

        return response()->json([], 200);
    }

    protected function handleExistingIntent($cart): JsonResponse
    {
        $intentModel = $cart->paymentIntents->first();

        if (! $intentModel) {
            abort(422, 'Unable to create order');
        }

        $stripeIntent = Stripe::fetchIntent($intentModel->intent_id);

        if ($stripeIntent->status !== PaymentIntent::STATUS_SUCCEEDED) {
            abort(422, 'Unable to create order');
        }

        $order = $intentModel->order ?: $cart->draftOrder;

        if (! $order) {
            abort(422, 'Unable to create order');
        }

        return $this->attemptAuthorization($stripeIntent, $order);
    }

    protected function handleAlreadyProcessedIntent($cart, $order): JsonResponse
    {
        $intentModel = $cart->paymentIntents->first();

        if (! $intentModel) {
            return response()->json(['message' => 'Unable to update payment intent.'], 422);
        }

        $stripeIntent = Stripe::fetchIntent($intentModel->intent_id);

        if ($stripeIntent->status !== PaymentIntent::STATUS_SUCCEEDED) {
            return response()->json(['message' => 'Unable to update payment intent.'], 422);
        }

        return $this->attemptAuthorization($stripeIntent, $order);
    }

    protected function attemptAuthorization($stripeIntent, $order): JsonResponse
    {
        $paymentResponse = Payments::driver('stripe')->order($order)->withData([
            'payment_intent' => $stripeIntent->id,
        ])->authorize();

        if ($paymentResponse->success) {
            CartSession::forget(delete: false);
            session()->put('checkout.order', $order->reference);

            return response()->json([
                'redirect' => route('checkout.success'),
            ]);
        }

        $order->update([
            'status' => 'requires-attention',
            'placed_at' => $order->placed_at ?? now(),
        ]);

        CartSession::forget(delete: false);
        session()->put('checkout.order', $order->reference);

        return response()->json([
            'redirect' => route('checkout.order-issue'),
        ]);
    }
}
