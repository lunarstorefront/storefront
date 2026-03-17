<?php

namespace Lunar\Storefront\Data;

use Lunar\Promotions\Models\Discount as DiscountModel;
use Spatie\LaravelData\Data;

/** @typescript */
class Discount extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $coupon,
    ) {}

    public static function fromModel(DiscountModel $discount): self
    {
        return new self(
            id: $discount->id,
            name: $discount->name,
            coupon: $discount->coupon,
        );
    }
}
