<?php

namespace Lunar\Storefront\Actions;

use Lunar\Catalog\Facades\Pricing;
use Lunar\Kernel\Models\Currency;
use Lunar\Sales\Facades\CartSession;

class SetCurrency
{
    public function set(?string $currencyCode = null): void
    {
        $currency = $currencyCode ? Currency::when(
            $currencyCode,
            fn ($query, $value) => $query->where('code', $value),
            fn ($query) => $query->where('default', true),
        )->first() : CartSession::getCurrency();

        CartSession::setCurrency($currency);
        Pricing::currency($currency);
    }
}
