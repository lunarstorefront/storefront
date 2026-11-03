<?php

namespace Lunar\Storefront\Actions\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Lunar\Core\Models\Collection;

class GetCollectionBySlug
{
    /**
     * @param  array<int, string>  $eager
     * @param  bool  $publishedOnly  Only return published collections (and, for a child, a published parent).
     */
    public function get(string $slug, ?string $child = null, array $eager = [], bool $publishedOnly = false): ?Collection
    {
        $targetSlug = $child ?: $slug;

        return Collection::whereHas(
            'defaultUrl',
            fn (Builder $query) => $query->where('slug', $targetSlug)
        )->when(
            $child,
            fn (Builder $query) => $query->whereHas('parent', function (Builder $builder) use ($slug, $publishedOnly) {
                $builder->whereHas('defaultUrl', fn ($query) => $query->where('slug', $slug))
                    ->when($publishedOnly, fn (Builder $builder) => $builder->whereVisible());
            }),
            fn (Builder $query) => $query->whereIsRoot()
        )->when($publishedOnly, fn (Builder $query) => $query->whereVisible())->with($eager)->first();
    }
}
