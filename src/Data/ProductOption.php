<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection;
use Lunar\Core\Models\ProductOption as ProductOptionModel;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ProductOption extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $handle,
        /** @var Lazy|ProductOptionValue[] */
        public Lazy|Collection $values
    ) {}

    public static function fromModel(ProductOptionModel $productOption): self
    {
        return new self(
            id: $productOption->id,
            name: (string) $productOption->translate('name'),
            handle: $productOption->handle,
            values: Lazy::whenLoaded('values', $productOption, fn () => ProductOptionValue::collect($productOption->values))
        );
    }
}
