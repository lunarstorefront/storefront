<?php

namespace Lunar\Storefront\Actions\Cart;

use Lunar\Core\DataTypes\ShippingOption as ShippingOptionValue;
use Lunar\Core\Facades\ShippingManifest;
use Lunar\Core\Models\Cart;
use Lunar\Storefront\Data\ShippingOption;

/**
 * The cheapest delivery option for the cart's delivery postcode: what a
 * basket shows for delivery before checkout. Prices come from the same
 * shipping manifest checkout uses, so the two always agree. Collection is
 * not delivery and is never the estimate.
 *
 * Null when the cart has no postcode yet or nothing delivers there; the
 * host can say why (a checkout DeliveryNotice, say).
 */
class EstimateShipping
{
    public function get(Cart $cart): ?ShippingOption
    {
        if (blank($cart->shippingAddress?->postcode)) {
            return null;
        }

        /** @var ShippingOptionValue|null $cheapest */
        $cheapest = ShippingManifest::getOptions($cart->calculate())
            ->reject(fn (ShippingOptionValue $option): bool => $option->collect)
            ->sortBy(fn (ShippingOptionValue $option): int => $option->getPrice()->value)
            ->first();

        if ($cheapest === null) {
            return null;
        }

        return new ShippingOption(
            name: $cheapest->getName(),
            description: $cheapest->getDescription(),
            identifier: $cheapest->getIdentifier(),
            price: $cheapest->getPrice()->value,
        );
    }
}
