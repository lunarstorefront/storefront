<?php

namespace Lunar\Storefront\Actions;

use Lunar\Catalog\Facades\Pricing;
use Lunar\Kernel\Contracts\StorefrontContextInterface;
use Lunar\Kernel\Models\Currency;
use Lunar\Kernel\Models\Region;
use Lunar\Sales\Facades\CartSession;

class SetCurrency
{
    public function set(?string $currencyCode = null): void
    {
        $currency = $currencyCode ? Currency::where('code', $currencyCode)->first() : null;

        if (! $currency) {
            return;
        }

        $region = Region::where('currency_id', $currency->id)->enabled()->first();

        if ($region) {
            app(StorefrontContextInterface::class)->setRegion($region);
        }

        CartSession::setCurrency($currency);
        Pricing::currency($currency);
    }
}
