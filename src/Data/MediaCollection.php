<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection as SpatieMediaCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class MediaCollection extends Data
{
    public function __construct(
        public string $name,
        public string $handle,
        /** @var Lazy|Media[] */
        public Lazy|Collection|SpatieMediaCollection $files,
    ) {}
}
