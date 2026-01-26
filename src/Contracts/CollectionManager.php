<?php

namespace Lunar\Storefront\Contracts;

use Lunar\Models\Contracts\Collection;
use Lunar\Models\Contracts\Product;

interface CollectionManager
{
    public function getBySlug(string $slug, ?string $child = null): ?Collection;

    public function getBreadcrumbs(Collection $collection): \Illuminate\Support\Collection;
}
