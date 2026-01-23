<?php

namespace Lunar\Storefront\Data;

use Spatie\LaravelData\Data;

/** @typescript  */
class MediaConversion extends Data
{
    public function __construct(
        public string $name,
        public string $url,
    ) {

    }
}
