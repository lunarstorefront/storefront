<?php

namespace Lunar\Storefront\Contracts;

use Lunar\Core\Models\Collection;
use Lunar\Storefront\Data\Breadcrumb;

interface CollectionManager
{
    public function getBySlug(string $slug, ?string $child = null): ?Collection;

    /**
     * @return \Illuminate\Support\Collection<int, Breadcrumb>
     */
    public function getBreadcrumbs(Collection $collection): \Illuminate\Support\Collection;
}
