<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection;
use Lunar\Catalog\DataObjects\PricingResponse as PricingResponseDto;
use Spatie\LaravelData\Data;

/** @typescript */
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
