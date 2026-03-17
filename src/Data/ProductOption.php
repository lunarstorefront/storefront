<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection;
use Lunar\Catalog\Models\ProductOption as ProductOptionModel;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/** @typescript */
class ProductOption extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $handle,
        /** @var ProductOptionValue[] */
        public Lazy|Collection $values
    ) {}

    public static function fromModel(ProductOptionModel $productOption): self
    {
        return new self(
            id: $productOption->id,
            name: (string) $productOption->name,
            handle: $productOption->handle,
            values: Lazy::whenLoaded('values', $productOption, fn () => ProductOptionValue::collect($productOption->values))
        );
    }
}
