<?php

namespace Lunar\Storefront\Contracts;

use Illuminate\Support\Collection;
use Lunar\Core\Contracts\Purchasable;
use Lunar\Core\DataObjects\PricingResponse;
use Lunar\Storefront\Data\Price;

interface PricingManager
{
    public function getPricing(Purchasable $purchasable, int $quantity = 1): ?PricingResponse;

    public function getPricingWithTax(Purchasable $purchasable, int $quantity = 1): ?PricingResponse;

    public function getQuantifiedPrice(PricingResponse $pricingResponse, int $quantity): ?Price;

    public function mapPriceBreaks(PricingResponse $pricingResponse): Collection;
}
