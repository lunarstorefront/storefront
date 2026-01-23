<?php

namespace Lunar\Storefront\Data;

use Spatie\LaravelData\Data;

/** @typescript */
class PricingResponse extends Data
{
    public function __construct(
        public Price $matched,
        /** @var Price[] */
        public \Illuminate\Support\Collection $priceBreaks,
    ) {}

    public static function fromDto(\Lunar\Base\DataTransferObjects\PricingResponse $response): self
    {
        return new self(
            matched: Price::from($response->matched),
            priceBreaks: Price::collect($response->priceBreaks)
        );
    }
}
