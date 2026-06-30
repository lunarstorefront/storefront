<?php

namespace Lunar\Storefront\Actions\Catalog;

use Illuminate\Support\Collection as LaravelCollection;
use Lunar\Core\Models\Collection;
use Lunar\Storefront\Data\Breadcrumb;

class GetCollectionBreadcrumbs
{
    /**
     * @var LaravelCollection<int, Breadcrumb>
     */
    private LaravelCollection $breadcrumbs;

    /**
     * @param  LaravelCollection<int, Breadcrumb>|null  $breadcrumbs
     */
    public function __construct(
        ?LaravelCollection $breadcrumbs = null
    ) {
        $this->breadcrumbs = $breadcrumbs ?: collect();
    }

    /**
     * @return LaravelCollection<int, Breadcrumb>
     */
    public function get(?Collection $collection): LaravelCollection
    {
        if ($collection) {
            $this->buildBreadcrumbs($collection);
        }

        return $this->breadcrumbs;
    }

    public function buildBreadcrumbs(Collection $collection): void
    {
        $ancestors = $collection->ancestors->values();

        foreach ($ancestors as $ancestor) {
            if (! $ancestor->defaultUrl) {
                continue;
            }

            $this->breadcrumbs->add(
                Breadcrumb::from([
                    'label' => (string) $ancestor->translate('name'),
                    'model' => 'collection',
                    'slug' => $ancestor->defaultUrl->slug,
                ])
            );
        }

        if ($collection->defaultUrl) {
            $this->breadcrumbs->add(
                Breadcrumb::from([
                    'label' => (string) $collection->translate('name'),
                    'model' => 'collection',
                    'slug' => $collection->defaultUrl->slug,
                ])
            );
        }
    }
}
