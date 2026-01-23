<?php

namespace Lunar\Storefront\Data;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/** @typescript */
class ProductOptionPermutation extends Data
{
    public bool $purchasable = true;

    public function __construct(
        public string $hash,
        public bool $hasVariant,
        public int $stock,
        public bool $backorder,
        /**
         * @var array<string,string>
         */
        public array $values,
        public array $valueNames
    ) {
        $this->purchasable = ($stock > 0 && $hasVariant) || $backorder;
    }
}
