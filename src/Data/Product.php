<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection;
use Lunar\Core\Models\Product as ProductModel;
use Lunar\Storefront\Data\Traits\HasAttributeData;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class Product extends Data
{
    use HasAttributeData;

    public function __construct(
        public string $name,
        public ?string $description,
        /** @var Collection<AttributeDataValue> */
        public Collection $attributeData,
        public Lazy|Media $thumbnail,
        /** @var Media[] */
        public Lazy|Collection $images,
        public ?Url $url = null,
    ) {}

    public static function fromModel(ProductModel $product): self
    {
        return new self(
            name: (string) $product->translate('name'),
            description: $product->translate('description'),
            attributeData: static::mapAttributes($product),
            thumbnail: Lazy::whenLoaded('thumbnail', $product, fn () => Media::from($product->thumbnail)),
            images: Lazy::whenLoaded('media', $product, fn () => Media::collect($product->media)),
            url: Url::from($product->defaultUrl)
        );
    }
}
