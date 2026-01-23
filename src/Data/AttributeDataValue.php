<?php

namespace Lunar\Storefront\Data;

/** @typescript */
class AttributeDataValue
{
    public function __construct(
        public string $name,
        public string $handle,
        public string $type,
        public mixed $value,
    ) {}
}
