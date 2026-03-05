<?php

namespace Lunar\Storefront\Normalizers;

use Spatie\LaravelData\Normalizers\Normalizer;

class CheckoutElementNormalizer implements Normalizer
{
    public function normalize(mixed $value): ?array
    {
        return $value->toArray();
    }
}
