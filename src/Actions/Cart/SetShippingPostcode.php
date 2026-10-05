<?php

namespace Lunar\Storefront\Actions\Cart;

use Lunar\Core\Models\Cart;
use Lunar\Core\Models\Country;

/**
 * Keeps a postcode on the cart's delivery address, as a basket delivery
 * estimate gives one, so shipping can be priced before checkout and the
 * checkout starts with it.
 *
 * A delivery address already at that postcode is left alone, so a shopper
 * re-checking the estimate keeps the full address they gave. A different
 * postcode replaces the address with the postcode alone: the old street no
 * longer goes with it.
 */
class SetShippingPostcode
{
    public function set(Cart $cart, string $postcode, Country $country): Cart
    {
        $postcode = (string) preg_replace('/\s+/', ' ', strtoupper(trim($postcode)));
        $current = $cart->shippingAddress;

        if ($current !== null
            && $current->country_id === $country->id
            && $this->compact((string) $current->postcode) === $this->compact($postcode)) {
            return $cart;
        }

        return $cart->setShippingAddress([
            'postcode' => $postcode,
            'country_id' => $country->id,
        ]);
    }

    private function compact(string $postcode): string
    {
        return str_replace(' ', '', strtoupper($postcode));
    }
}
