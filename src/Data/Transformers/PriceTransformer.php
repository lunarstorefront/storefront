<?php

namespace Lunar\Storefront\Data\Transformers;

use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

/**
 * Transforms integer price values.
 *
 * In v2, prices are plain integers so this transformer simply passes
 * through the value. Retained for backward compatibility with any
 * consumers that register it.
 */
class PriceTransformer implements Transformer
{
    final public function transform(DataProperty $property, mixed $value, TransformationContext $context): int
    {
        return (int) $value;
    }
}
