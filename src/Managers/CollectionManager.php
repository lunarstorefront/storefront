<?php

namespace Lunar\Storefront\Managers;

use Lunar\Storefront\Actions\Catalog\GetCollectionBreadcrumbs;
use Lunar\Storefront\Actions\Catalog\GetCollectionBySlug;
use Lunar\Storefront\Facades\Storefront;

class CollectionManager implements \Lunar\Storefront\Contracts\CollectionManager
{
    public function getBySlug(string $slug, ?string $child = null): ?\Lunar\Models\Contracts\Collection
    {
        return (new GetCollectionBySlug)->get($slug, $child, [
            'defaultUrl', 'children.defaultUrl',
        ]);
    }

    public function getBreadcrumbs(\Lunar\Models\Contracts\Collection $collection): \Illuminate\Support\Collection
    {
        return (new GetCollectionBreadcrumbs)->get($collection);
    }

    public function getShowPageProps(\Lunar\Models\Contracts\Collection $collection, ?string $sort, int $perPage): array
    {
        $breadcrumbs = $this->getBreadcrumbs($collection);
        $results = Storefront::search()->getResults(collection: $collection, sort: $sort, perPage: $perPage);

        return [
            'collection' => \Lunar\Storefront\Data\Collection::from($collection),
            'breadcrumbs' => $breadcrumbs,
            'results' => $results,
        ];
    }
}
