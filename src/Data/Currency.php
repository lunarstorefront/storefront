<?php

namespace Lunar\Storefront\Data;

use Lunar\Core\Models\Currency as CurrencyModel;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
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
