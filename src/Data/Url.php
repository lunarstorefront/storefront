<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection;
use Lunar\Storefront\Data\Traits\HasAttributeData;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/** @typescript  */
class Url extends Data
{
    use HasAttributeData;

    public function __construct(
        public string $slug,
        public bool $isDefault,
    ) {

    }

    public static function fromModel(\Lunar\Models\Contracts\Url $url): self
    {
        return new self(
            slug: $url->slug,
            isDefault: $url->default,
        );
    }
}
