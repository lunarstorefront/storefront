<?php

namespace Lunar\Storefront\Data;

use Spatie\LaravelData\Data;

/** @typescript  */
class Checkout extends Data
{
    public function __construct(
        public \Illuminate\Support\Collection $elements
    ) {}

    public static function fromArray(array $data): self
    {
        $elements = collect($data['elements'] ?? [])->map(
            fn (\Lunar\Checkout\CheckoutElement $element) => $element->toArray()
        );

        return new self(
            elements: CheckoutElement::collect($elements),
        );
    }
}
