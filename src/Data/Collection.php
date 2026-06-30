<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection as LaravelCollection;
use Lunar\Core\Models\Collection as CollectionModel;
use Lunar\Storefront\Data\Traits\HasAttributeData;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class Collection extends Data
{
    use HasAttributeData;

    public function __construct(
        public string $name,
        public string|Optional|null $description,
        public Lazy|Media $thumbnail,
        public ?Url $url,
        /** @var LaravelCollection<Collection> */
        public Lazy|LaravelCollection $children,
    ) {}

    public static function fromModel(CollectionModel $collection): self
    {
        return new self(
            name: (string) $collection->translate('name'),
            description: $collection->translate('description'),
            thumbnail: Lazy::whenLoaded('thumbnail', $collection, fn () => Media::from($collection->thumbnail)),
            url: $collection->defaultUrl ? Url::from($collection->defaultUrl) : null,
            children: Lazy::whenLoaded('children', $collection, fn () => self::collect($collection->children)),
        );
    }
}
