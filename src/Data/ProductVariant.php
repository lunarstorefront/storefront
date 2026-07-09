<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection;
use Lunar\Core\Models\ProductVariant as ProductVariantModel;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ProductVariant extends Data
{
    public function __construct(
        public string $id,
        public string $sku,
        public int $stock,
        /** @var Lazy|ProductOptionValue[] */
        public Lazy|Collection $values,
    ) {}

    public static function fromModel(ProductVariantModel $variant): self
    {
        return new self(
            id: $variant->id,
            sku: $variant->sku,
            stock: $variant->stock,
            values: Lazy::whenLoaded('values', $variant, fn () => ProductOptionValue::collect($variant->values))
        );
    }
}
