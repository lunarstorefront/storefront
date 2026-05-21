<?php

namespace Lunar\Storefront\Contracts;

use Lunar\Catalog\DataObjects\PricingResponse;
use Lunar\Catalog\DataObjects\TaxAwarePricingResponse;
use Lunar\Kernel\Contracts\Purchasable;
use Lunar\Storefront\Data\Price;

interface PricingManager
{
    public function getPricing(Purchasable $purchasable, int $quantity = 1): ?PricingResponse;

    public function getPricingWithTax(Purchasable $purchasable, int $quantity = 1): ?TaxAwarePricingResponse;

    public function getQuantifiedPrice(PricingResponse|TaxAwarePricingResponse $pricingResponse, int $quantity): ?Price;
}
