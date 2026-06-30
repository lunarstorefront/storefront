<?php

namespace Lunar\Storefront\Data;

use Lunar\Core\Models\Url as UrlModel;
use Lunar\Storefront\Data\Traits\HasAttributeData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class Url extends Data
{
    use HasAttributeData;

    public function __construct(
        public string $slug,
        public bool $isDefault,
    ) {}

    public static function fromModel(UrlModel $url): self
    {
        return new self(
            slug: $url->slug,
            isDefault: $url->default,
        );
    }
}
