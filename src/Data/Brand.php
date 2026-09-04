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
        public Lazy|Media|null $logo,
        public Lazy|Url $url,
    ) {}

    public static function fromModel(BrandModel $brand): self
    {
        return new self(
            name: $brand->name,
            attributeData: static::mapAttributes($brand),
            productsCount: $brand->products_count ?: 0,
            logo: Lazy::when(
                fn () => $brand->relationLoaded('media') || $brand->relationLoaded('thumbnail'),
                fn () => static::logoMedia($brand),
            ),
            url: Lazy::whenLoaded('defaultUrl', $brand, fn () => Url::from($brand->defaultUrl)),
        );
    }

    /**
     * The brand mark: the dedicated `logo` media collection when one is
     * uploaded, falling back to the brand's primary image. Resolved from the
     * already-loaded `media` relation where possible so brand listings stay
     * free of N+1 queries; falls back to the `thumbnail` relation otherwise.
     */
    protected static function logoMedia(BrandModel $brand): ?Media
    {
        $logo = $brand->relationLoaded('media')
            ? ($brand->getFirstMedia('logo') ?? $brand->getFirstMedia(
                config('lunar.media.collection'),
                fn ($media) => (bool) $media->getCustomProperty('primary'),
            ))
            : $brand->thumbnail;

        return $logo ? Media::from($logo) : null;
    }
}
