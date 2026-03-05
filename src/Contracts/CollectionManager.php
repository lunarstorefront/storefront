<?php

namespace Lunar\Storefront\Contracts;

use Lunar\Models\Contracts\Collection;

interface CollectionManager
{
    public function getBySlug(string $slug, ?string $child = null): ?Collection;

    public function getBreadcrumbs(Collection $collection): \Illuminate\Support\Collection;

    public function getShowPageProps(Collection $collection, ?string $sort, int $perPage): array;
}
