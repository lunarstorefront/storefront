<?php

namespace Lunar\Storefront\Managers;

use Lunar\Storefront\Actions\Catalog\GetCollectionBreadcrumbs;
use Lunar\Storefront\Actions\Catalog\GetCollectionBySlug;

class CollectionManager implements \Lunar\Storefront\Contracts\CollectionManager
{
    public function getBySlug(string $slug, ?string $child = null): ?\Lunar\Catalog\Models\Collection
    {
        return (new GetCollectionBySlug)->get($slug, $child, [
            'defaultUrl', 'children.defaultUrl',
        ]);
    }

    public function getBreadcrumbs(\Lunar\Catalog\Models\Collection $collection): \Illuminate\Support\Collection
    {
        return (new GetCollectionBreadcrumbs)->get($collection);
    }
}
