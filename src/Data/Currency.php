<?php

namespace Lunar\Storefront\Data;

use Lunar\Kernel\Models\Currency as CurrencyModel;
use Spatie\LaravelData\Data;

/** @typescript */
class Currency extends Data
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
    ) {}

    public static function fromModel(CurrencyModel $currency): self
    {
        return new self(
            id: $currency->id,
            code: $currency->code,
            name: $currency->name,
        );
    }
}
