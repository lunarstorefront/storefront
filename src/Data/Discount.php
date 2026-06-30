<?php

namespace Lunar\Storefront\Data;

use Lunar\Core\Models\Discount as DiscountModel;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
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
