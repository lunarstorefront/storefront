<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection;
use Lunar\Storefront\Data\Traits\HasAttributeData;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/** @typescript */
class Brand extends Data
{
    use HasAttributeData;

    public function __construct(
        public string $name,
        /** @var Collection<AttributeDataValue> */
        public Collection $attributeData,
        public Lazy|Media $logo,
        public Lazy|Url $url,
    ) {}

    public static function fromModel(\Lunar\Models\Contracts\Brand $brand): self
    {
        return new self(
            name: $brand->name,
            attributeData: static::mapAttributes($brand),
            logo: Lazy::whenLoaded('thumbnail', $brand, fn () => Media::from($brand->thumbnail)),
            url: Lazy::whenLoaded('defaultUrl', $brand, fn () => Url::from($brand->defaultUrl)),
        );
    }
}
