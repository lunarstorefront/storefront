<?php

namespace Lunar\Storefront\Actions\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Lunar\Storefront\Data\Collection;

class GetCollectionTree
{
    public function get(string $group = 'main', int $maxDepth = 3)
    {
        $collections = \Lunar\Catalog\Models\Collection::whereHas(
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
