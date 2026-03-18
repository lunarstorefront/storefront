<?php

namespace Lunar\Storefront\Managers;

use Illuminate\Support\Collection;
use Lunar\Catalog\DataObjects\PricingResponse;
use Lunar\Catalog\Facades\Pricing;
use Lunar\Kernel\Contracts\Purchasable;
use Lunar\Sales\Facades\CartSession;
use Lunar\Storefront\Actions\Catalog\GetQuantifiedPrice;
use Lunar\Storefront\Actions\Catalog\MapProductPriceBreaks;
use Lunar\Storefront\Data\Price;

class PricingManager implements \Lunar\Storefront\Contracts\PricingManager
{
    public function getPricing(Purchasable $purchasable, int $quantity = 1): ?PricingResponse
    {
        try {
            return Pricing::for($purchasable)->currency(
                CartSession::getCurrency()
            )->qty($quantity)->get();
        } catch (\Exception $e) {
            report($e);
        }

        return null;
    }

    public function mapPriceBreaks(PricingResponse $pricingResponse): Collection
    {
        return (new MapProductPriceBreaks)->map($pricingResponse);
    }

    public function getQuantifiedPrice(PricingResponse $pricingResponse, int $quantity): Price
    {
        return (new GetQuantifiedPrice)->get($pricingResponse, $quantity);
    }
}
