<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection as LaravelCollection;
use Lunar\Catalog\Models\Collection as CollectionModel;
use Lunar\Storefront\Data\Traits\HasAttributeData;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/** @typescript  */
class Collection extends Data
{
    use HasAttributeData;

    public function __construct(
        public string $name,
        public ?string $description,
        public Lazy|Media $thumbnail,
        /** @var LaravelCollection<Collection> */
        public Lazy|LaravelCollection $children,
        public ?Url $url,
    ) {

    }

    public static function fromModel(CollectionModel $collection): self
    {
        return new self(
            name: $collection->attr('name'),
            description: $collection->attr('description'),
            thumbnail: Lazy::whenLoaded('thumbnail', $collection, fn () => Media::from($collection->thumbnail)),
            children: Lazy::whenLoaded('children', $collection, fn () => self::collect($collection->children)),
            url: $collection->defaultUrl ? Url::from($collection->defaultUrl) : null,
        );
    }
}
