<?php

namespace Lunar\Storefront\Actions\Checkout;

use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Model;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\OrderAddress;

final class GetOrderContactEmail
{
    /**
     * Where order updates go for an order the checkout just handed over.
     *
     * A guest's email can live only on the checkout session, so it is the
     * fallback when neither order address carries one.
     */
    public function handle(Order $order, Session $session): ?string
    {
        $order->loadMissing(['billingAddress', 'shippingAddress', 'user']);

        foreach ([$order->billingAddress, $order->shippingAddress] as $address) {
            if ($address instanceof OrderAddress && filled($address->contact_email)) {
                return $address->contact_email;
            }
        }

        return $this->checkoutSessionEmail($session) ?? ($order->user?->email ?: null);
    }

    private function checkoutSessionEmail(Session $session): ?string
    {
        $uuid = $session->get('lunar.checkout.completed.uuid');

        if (! is_string($uuid) || ! class_exists($this->checkoutSessionModel())) {
            return null;
        }

        /** @var class-string<Model> $model */
        $model = $this->checkoutSessionModel();
        $email = $model::query()->where('uuid', $uuid)->first()?->getAttribute('customer_email');

        return is_string($email) && $email !== '' ? $email : null;
    }

    private function checkoutSessionModel(): string
    {
        return 'Lunar\Checkout\Models\CheckoutSession';
    }
}
