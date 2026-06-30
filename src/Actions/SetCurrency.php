<?php

namespace Lunar\Storefront\Actions;

use Lunar\Core\Facades\CartSession;
use Lunar\Core\Facades\Pricing;
use Lunar\Core\Models\Currency;

class SetCurrency
{
    public function set(?string $currencyCode = null): void
    {
        $currency = $currencyCode ? Currency::where('code', $currencyCode)->first() : null;

        if (! $currency) {
            return;
        }

        CartSession::setCurrency($currency);
        Pricing::currency($currency);
    }
}
