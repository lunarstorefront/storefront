<?php

namespace Lunar\Storefront\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lunar\Catalog\Models\Brand;

interface BrandManager
{
    public function getBySlug(string $slug): Brand;

    /**
     * @return LengthAwarePaginator<int, Brand>
     */
    public function getPaginated(int $perPage = 150): LengthAwarePaginator;
}
