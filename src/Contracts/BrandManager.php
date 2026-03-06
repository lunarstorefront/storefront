<?php

namespace Lunar\Storefront\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lunar\Models\Brand;

interface BrandManager
{
    public function getBySlug(string $slug): Brand;

    public function getPaginated(int $perPage = 150): LengthAwarePaginator;
}
