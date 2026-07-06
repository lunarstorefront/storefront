<?php

namespace Lunar\Storefront\Actions\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection as LaravelCollection;
use Lunar\Storefront\Data\Collection;

class GetCollectionTree
{
    /**
     * @param  array<int, string>  $eager  Relations to eager load onto each collection.
     * @return LaravelCollection<int, Collection>
     */
    public function get(string $group = 'main', int $maxDepth = 3, array $eager = ['defaultUrl']): LaravelCollection
    {
        $collections = \Lunar\Core\Models\Collection::whereHas(
            'group',
            fn (Builder $builder) => $builder->where('handle', $group)
        )->with($eager)
            ->withDepth()
            ->defaultOrder()
            ->get()
            ->filter(fn ($collection) => $collection->depth < $maxDepth)
            ->toTree();

        return Collection::collect($collections);
    }
}
