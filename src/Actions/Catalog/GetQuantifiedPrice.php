<?php

namespace Lunar\Storefront\Actions\Catalog;

use Lunar\Core\DataObjects\PricingResponse;
use Lunar\Storefront\Data\Price;

class GetQuantifiedPrice
{
    public function get(PricingResponse $pricingResponse, int $quantity = 1): ?Price
    {
        return Price::fromModel($pricingResponse->matched, $quantity);
    }
}
