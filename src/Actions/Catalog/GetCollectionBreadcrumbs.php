<?php

namespace Lunar\Storefront\Actions\Catalog;

use Lunar\Storefront\Data\Breadcrumb;
use Illuminate\Support\Collection as LaravelCollection;
use Lunar\Models\Collection;

class GetCollectionBreadcrumbs
{
    public function __construct(
        private ?LaravelCollection $breadcrumbs = null
    ) {
        $this->breadcrumbs = $breadcrumbs ?: collect();
    }

    public function get(?Collection $collection): LaravelCollection
    {
        if ($collection) {
            $this->buildBreadcrumbs($collection);
        }

        return $this->breadcrumbs;
    }

    public function buildBreadcrumbs(Collection $collection): void
    {
        foreach ($collection->ancestors as $ancestor) {
            $this->breadcrumbs->add(
                Breadcrumb::from([
                    'label' => $ancestor->attr('name'),
                    'model' => 'collection',
                    'slug' => $ancestor->defaultUrl->slug,
                ])
            );
        }


        $this->breadcrumbs->add(
            Breadcrumb::from([
                'label' => $collection->attr('name'),
                'model' => 'collection',
                'slug' => $collection->defaultUrl->slug,
            ])
        );
    }
}
