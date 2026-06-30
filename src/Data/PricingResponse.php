<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection;
use Lunar\Core\DataObjects\PricingResponse as PricingResponseDto;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PricingResponse extends Data
{
    public function __construct(
        public ?Price $matched,
        /** @var Price[] */
        public Collection $priceBreaks,
    ) {}

    public static function fromDto(PricingResponseDto $response): self
    {
        return new self(
            matched: $response->matched ? Price::fromModel($response->matched) : null,
            priceBreaks: $response->priceBreaks->map(
                fn ($price) => Price::fromModel($price)
            )
        );
    }
}
