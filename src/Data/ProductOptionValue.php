<?php

namespace Lunar\Storefront\Data;

use Lunar\Core\Models\ProductOptionValue as ProductOptionValueModel;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ProductOptionValue extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public Lazy|ProductOption $productOption,
    ) {}

    public static function fromModel(ProductOptionValueModel $productOptionValue): self
    {
        return new self(
            id: $productOptionValue->id,
            name: (string) $productOptionValue->translate('name'),
            productOption: Lazy::whenLoaded('option', $productOptionValue, fn () => ProductOption::from($productOptionValue->option))
        );
    }
}
