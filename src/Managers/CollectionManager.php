<?php

namespace Lunar\Storefront\Managers;

use Illuminate\Support\Collection;
use Lunar\Models\Contracts\Product;
use Lunar\Storefront\Actions\Catalog\GetCollectionBreadcrumbs;
use Lunar\Storefront\Actions\Catalog\GetCollectionBySlug;
use Lunar\Storefront\Actions\Catalog\GetProductOptionPermutations;
use Lunar\Storefront\Actions\Catalog\GetProductOptions;

class CollectionManager implements \Lunar\Storefront\Contracts\CollectionManager
{
    public function getBySlug(string $slug, ?string $child = null): ?\Lunar\Models\Contracts\Collection
    {
        return (new GetCollectionBySlug)->get($slug, $child, [
            'defaultUrl', 'children.defaultUrl'
        ]);
    }

    public function getBreadcrumbs(\Lunar\Models\Contracts\Collection $collection): \Illuminate\Support\Collection
    {
        return (new GetCollectionBreadcrumbs)->get($collection);
    }
}
