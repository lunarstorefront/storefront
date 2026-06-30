<?php

namespace Lunar\Storefront\Managers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Lunar\Core\Models\Brand;

class BrandManager implements \Lunar\Storefront\Contracts\BrandManager
{
    public function getBySlug(string $slug): Brand
    {
        return Brand::with(['thumbnail'])
            ->whereHas('defaultUrl', fn (Builder $query) => $query->where('slug', $slug))
            ->firstOrFail();
    }

    public function getPaginated(int $perPage = 150): LengthAwarePaginator
    {
        return Brand::whereHas('defaultUrl')
            ->with(['defaultUrl', 'thumbnail'])
            ->orderBy('name')
            ->paginate($perPage);
    }
}
