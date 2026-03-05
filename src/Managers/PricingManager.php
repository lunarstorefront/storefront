<?php

namespace Lunar\Storefront\Managers;

use Illuminate\Support\Collection;
use Lunar\Base\DataTransferObjects\PricingResponse;
use Lunar\Base\Purchasable;
use Lunar\Facades\Pricing;
use Lunar\Facades\StorefrontSession;
use Lunar\Storefront\Actions\Catalog\GetQuantifiedPrice;
use Lunar\Storefront\Actions\Catalog\MapProductPriceBreaks;
use Lunar\Storefront\Data\Price;

class PricingManager implements \Lunar\Storefront\Contracts\PricingManager
{
    public function getPricing(Purchasable $purchasable, int $quantity = 1): ?PricingResponse
    {
        try {
            return Pricing::for($purchasable)->currency(
                StorefrontSession::getCurrency()
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
