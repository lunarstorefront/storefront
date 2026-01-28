<?php

namespace Lunar\Storefront\Contracts;

use Lunar\Base\DataTransferObjects\PricingResponse;
use Lunar\Base\Purchasable;
use Lunar\Storefront\Data\Price;

interface PricingManager
{
    public function getPricing(Purchasable $purchasable, int $quantity = 1);

    public function getQuantifiedPrice(PricingResponse $pricingResponse, int $quantity): Price;
}
