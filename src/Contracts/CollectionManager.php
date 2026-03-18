<?php

namespace Lunar\Storefront\Contracts;

use Lunar\Catalog\Models\Collection;

interface CollectionManager
{
    public function getBySlug(string $slug, ?string $child = null): ?Collection;

    public function getBreadcrumbs(Collection $collection): \Illuminate\Support\Collection;
}
