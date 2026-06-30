<?php

namespace Lunar\Storefront\Actions\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection as LaravelCollection;
use Lunar\Storefront\Data\Collection;

class GetCollectionTree
{
    /**
     * @return LaravelCollection<int, Collection>
     */
    public function get(string $group = 'main', int $maxDepth = 3): LaravelCollection
    {
        $collections = \Lunar\Core\Models\Collection::whereHas(
            'group',
            fn (Builder $builder) => $builder->where('handle', $group)
        )->with(['defaultUrl'])
            ->withDepth()
            ->defaultOrder()
            ->get()
            ->filter(fn ($collection) => $collection->depth < $maxDepth)
            ->toTree();

        return Collection::collect($collections);
    }
}
