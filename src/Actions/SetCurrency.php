<?php

namespace Lunar\Storefront\Actions;

use Lunar\Facades\CartSession;
use Lunar\Facades\Pricing;
use Lunar\Facades\StorefrontSession;
use Lunar\Models\Currency;

class SetCurrency
{
    public function set(?string $currencyCode = null): void
    {
        $currency = $currencyCode ? Currency::when(
            $currencyCode,
            fn ($query, $value) => $query->where('code', $value),
            fn ($query) => $query->where('default', true),
        )->first() : StorefrontSession::getCurrency();

        CartSession::setCurrency($currency);
        Pricing::currency($currency);
        StorefrontSession::setCurrency($currency);
    }
}
