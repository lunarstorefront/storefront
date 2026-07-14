<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection;
use Lunar\Core\Models\Brand as BrandModel;
use Lunar\Storefront\Data\Traits\HasAttributeData;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class Brand extends Data
{
    use HasAttributeData;

    public function __construct(
        public string $name,
        /** @var Collection<AttributeDataValue> */
        public Collection $attributeData,
        public int $productsCount,
        public Lazy|Media $logo,
        public Lazy|Url $url,
    ) {}

    public static function fromModel(BrandModel $brand): self
    {
        return new self(
            name: $brand->name,
            attributeData: static::mapAttributes($brand),
            productsCount: $brand->products_count ?: 0,
            logo: Lazy::whenLoaded('thumbnail', $brand, fn () => Media::from($brand->thumbnail)),
            url: Lazy::whenLoaded('defaultUrl', $brand, fn () => Url::from($brand->defaultUrl)),
        );
    }
}
