<?php

namespace Lunar\Storefront\Data;

use Lunar\Kernel\Models\Url as UrlModel;
use Lunar\Storefront\Data\Traits\HasAttributeData;
use Spatie\LaravelData\Data;

/** @typescript  */
class Url extends Data
{
    use HasAttributeData;

    public function __construct(
        public string $slug,
        public bool $isDefault,
    ) {

    }

    public static function fromModel(UrlModel $url): self
    {
        return new self(
            slug: $url->slug,
            isDefault: $url->default,
        );
    }
}
