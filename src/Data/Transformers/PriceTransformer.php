<?php

namespace Lunar\Storefront\Data\Transformers;

use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

class PriceTransformer implements Transformer
{
    final public function transform(DataProperty $property, mixed $value, TransformationContext $context): array
    {
        return [
            'decimal' => $value->decimal,
            'decimal_unit' => $value->unitDecimal(),
            'formatted' => $value->formatted,
            'formatted_unit' => $value->unitFormatted,
            'value' => $value->value,
            'unit_qty' => $value->unitQty,
        ];
    }
}
