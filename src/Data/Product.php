<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection;
use Lunar\Catalog\Models\Product as ProductModel;
use Lunar\Storefront\Data\Traits\HasAttributeData;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/** @typescript  */
class Product extends Data
{
    use HasAttributeData;

    public function __construct(
        public string $name,
        public ?string $description,
        /** @var Collection<AttributeDataValue> */
        public Collection $attributeData,
        public Lazy|Media $thumbnail,
        public Lazy|Collection $images,
        public ?Url $url = null,
    ) {

    }

    public static function fromModel(ProductModel $product): self
    {
        return new self(
            name: $product->attr('name'),
            description: $product->attr('description'),
            attributeData: static::mapAttributes($product),
            thumbnail: Lazy::whenLoaded('thumbnail', $product, fn () => Media::from($product->thumbnail)),
            images: Lazy::whenLoaded('images', $product, fn () => Media::collect($product->images)),
            url: Url::from($product->defaultUrl)
        );
    }
}
