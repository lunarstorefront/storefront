<?php

namespace Lunar\Storefront\Managers;

use Illuminate\Support\Collection;
use Lunar\Core\Contracts\Purchasable;
use Lunar\Core\DataObjects\PricingResponse;
use Lunar\Core\Facades\Pricing;
use Lunar\Core\Facades\StorefrontSession;
use Lunar\Storefront\Actions\Catalog\GetQuantifiedPrice;
use Lunar\Storefront\Actions\Catalog\MapProductPriceBreaks;
use Lunar\Storefront\Data\Price;

class PricingManager implements \Lunar\Storefront\Contracts\PricingManager
{
    /**
     * Prices with the storefront session's context: its currency, which the
     * session carries between requests, and the signed-in customer's groups,
     * so a trade customer sees the price their basket will charge. Lunar's
     * pricing no longer resolves either by itself.
     */
    public function getPricing(Purchasable $purchasable, int $quantity = 1): ?PricingResponse
    {
        try {
            return Pricing::for($purchasable)
                ->using(StorefrontSession::context())
                ->qty($quantity)
                ->get();
        } catch (\Exception $e) {
            report($e);
        }

        return null;
    }

    public function getPricingWithTax(Purchasable $purchasable, int $quantity = 1): ?PricingResponse
    {
        return $this->getPricing($purchasable, $quantity);
    }

    public function mapPriceBreaks(PricingResponse $pricingResponse): Collection
    {
        return (new MapProductPriceBreaks)->map($pricingResponse);
    }

    public function getQuantifiedPrice(PricingResponse $pricingResponse, int $quantity): ?Price
    {
        return (new GetQuantifiedPrice)->get($pricingResponse, $quantity);
    }
}
