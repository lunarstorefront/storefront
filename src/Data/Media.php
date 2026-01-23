<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

/** @typescript  */
class Media extends Data
{
    public function __construct(
        public int|string $id,
        public string $name,
        public string $collectionName,
        public string $mimeType,
        public string $original,
        public bool $isPrimary,
        /** @var Collection<MediaConversion> */
        public Collection $conversions
    ) {

    }

    public static function fromModel(\Spatie\MediaLibrary\MediaCollections\Models\Media $media): self
    {
        return new self(
            id: $media->getKey(),
            name: $media->getCustomProperty('name') ?: '',
            collectionName: $media->collection_name,
            mimeType: $media->mime_type,
            original: $media->getUrl(),
            isPrimary: $media->getCustomProperty('primary'),
            conversions: MediaConversion::collect(
                collect($media->getGeneratedConversions())->map(
                    fn ($active, $conversion) => [
                        'name' => $conversion,
                        'url' => $media->getUrl($conversion),
                    ]
                )
            )
        );
    }
}
