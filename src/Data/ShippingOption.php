<?php

namespace Lunar\Storefront\Data;

use Spatie\LaravelData\Data;

/** @typescript */
class ShippingOption extends Data
{
    public function __construct(
        public string $name,
        public ?string $description,
        public ?string $identifier,
        public \Lunar\DataTypes\Price $price,
        public ?string $cutoff = null,
    ) {}
}
