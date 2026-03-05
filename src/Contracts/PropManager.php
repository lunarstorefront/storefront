<?php

namespace Lunar\Storefront\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lunar\Storefront\PropData;
use Lunar\Storefront\StorefrontPage;

interface PropManager
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(StorefrontPage|string $page, ?Model $record = null): array;

    public function add(array|Collection|PropData $propData): void;
}
