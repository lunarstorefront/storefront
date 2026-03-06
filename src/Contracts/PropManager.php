<?php

namespace Lunar\Storefront\Contracts;

use Illuminate\Support\Collection;
use Lunar\Storefront\PropData;
use Lunar\Storefront\StorefrontPage;

interface PropManager
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(StorefrontPage|string $page, mixed $record = null): array;

    public function add(array|Collection|PropData $propData): void;
}
