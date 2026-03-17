<?php

namespace Lunar\Storefront\Data;

use Lunar\Catalog\Models\ProductOptionValue as ProductOptionValueModel;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/** @typescript */
class ProductOptionValue extends Data
{
    public function __construct(
        public string             $id,
        public string             $name,
        public Lazy|ProductOption $productOption,
    ) {}

    public static function fromModel(ProductOptionValueModel $productOptionValue): self
    {
        return new self(
            id: $productOptionValue->id,
            name: (string) $productOptionValue->name,
            productOption: Lazy::whenLoaded('option', $productOptionValue, fn () => ProductOption::from($productOptionValue->option))
        );
    }
}
