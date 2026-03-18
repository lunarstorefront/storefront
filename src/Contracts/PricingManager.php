<?php

namespace Lunar\Storefront\Contracts;

use Lunar\Catalog\DataObjects\PricingResponse;
use Lunar\Kernel\Contracts\Purchasable;
use Lunar\Storefront\Data\Price;

interface PricingManager
{
    public function getPricing(Purchasable $purchasable, int $quantity = 1): ?PricingResponse;

    public function getQuantifiedPrice(PricingResponse $pricingResponse, int $quantity): Price;
}
