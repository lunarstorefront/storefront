<?php

namespace Lunar\Storefront\Actions\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Lunar\Core\Models\Collection;

class GetCollectionBySlug
{
    /**
     * @param  array<int, string>  $eager
     */
    public function get(string $slug, ?string $child = null, array $eager = []): ?Collection
    {
        $targetSlug = $child ?: $slug;

        return Collection::whereHas(
            'defaultUrl',
            fn (Builder $query) => $query->where('slug', $targetSlug)
        )->when(
            $child,
            fn (Builder $query) => $query->whereHas('parent', function (Builder $builder) use ($slug) {
                $builder->whereHas('defaultUrl', fn ($query) => $query->where('slug', $slug));
            }),
            fn (Builder $query) => $query->whereIsRoot()
        )->with($eager)->first();
    }
}
