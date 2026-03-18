<?php

namespace Lunar\Storefront\Actions\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lunar\Catalog\Models\Collection;

class GetCollectionBySlug
{
    public function get(string $slug, ?string $child = null, array $eager = []): ?Collection
    {
        return Collection::whereHas(
            'urls',
            fn (Builder $query) => $query->where('slug', $child ?: $slug)->where('default', true)
        )->when(
            $child,
            fn (Builder $query) => $query->whereHas('parent', function (Builder $builder) use ($slug) {
                $builder->whereHas('urls', fn ($query) => $query->where('slug', $slug)->where('default', true));
            }),
            fn ($query) => $query->whereIsRoot()
        )->with($eager)->first();
    }
}
