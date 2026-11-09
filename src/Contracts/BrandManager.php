<?php

namespace Lunar\Storefront\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lunar\Core\Models\Brand;

interface BrandManager
{
    public function getBySlug(string $slug): Brand;

    /**
     * Brands listed by the letter they file under, A to Z and then `#`, and by name
     * within it. A name files under the letter its first character starts with once
     * its accents are folded (NFD, as `String.prototype.normalize('NFD')` does), so
     * "Évian" is under E. Every other name ("18 Studio", "Øresund", "鬼谷工作室")
     * is under `#`.
     *
     * @return LengthAwarePaginator<int, Brand>
     */
    public function getPaginated(int $perPage = 150): LengthAwarePaginator;
}
