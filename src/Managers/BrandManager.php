<?php

namespace Lunar\Storefront\Managers;

use Illuminate\Database\Eloquent\Builder;
use Lunar\Models\Brand;
use Lunar\Storefront\Facades\Storefront;

class BrandManager implements \Lunar\Storefront\Contracts\BrandManager
{
    public function getIndexPageProps(): array
    {
        $brands = Brand::whereHas('defaultUrl')
            ->with(['defaultUrl', 'thumbnail'])
            ->orderBy('name')
            ->paginate(150);

        return [
            'brands' => \Lunar\Storefront\Data\Brand::collect($brands),
        ];
    }

    public function getShowPageProps(string $slug, ?string $sort, int $perPage): array
    {
        $brand = Brand::with(['thumbnail'])
            ->whereHas('defaultUrl', fn (Builder $query) => $query->where('slug', $slug))
            ->firstOrFail();

        $results = Storefront::search()->getResults(
            filters: ['brand' => $brand->name],
            sort: $sort,
            perPage: $perPage,
        );

        return [
            'brand' => \Lunar\Storefront\Data\Brand::from($brand),
            'results' => $results,
        ];
    }
}
