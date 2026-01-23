<?php

namespace Lunar\Storefront\Data;

use Spatie\LaravelData\Data;

/** @typescript */
class Breadcrumb extends Data
{
    public function __construct(
        public string $label,
        public string $model,
        public string $slug,
    ) {}
}
