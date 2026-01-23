<?php

namespace Lunar\Storefront\Data;

use Spatie\LaravelData\Data;

/** @typescript */
class Currency extends Data
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
    ) {}

    public static function fromModel(\Lunar\Models\Contracts\Currency $currency): self
    {
        return new self(
            id: $currency->id,
            code: $currency->code,
            name: $currency->name,
        );
    }
}
