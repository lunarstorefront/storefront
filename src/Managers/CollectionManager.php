<?php

namespace Lunar\Storefront\Managers;

use Illuminate\Support\Collection;
use Lunar\Storefront\Actions\Catalog\GetCollectionBreadcrumbs;
use Lunar\Storefront\Actions\Catalog\GetCollectionBySlug;

class CollectionManager implements \Lunar\Storefront\Contracts\CollectionManager
{
    public function getBySlug(string $slug, ?string $child = null): ?\Lunar\Core\Models\Collection
    {
        return (new GetCollectionBySlug)->get($slug, $child, [
            'defaultUrl', 'children.defaultUrl',
        ]);
    }

    public function getBreadcrumbs(\Lunar\Core\Models\Collection $collection): Collection
    {
        return (new GetCollectionBreadcrumbs)->get($collection);
    }
}
