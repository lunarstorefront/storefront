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
            ->orderByRaw($this->startsWithLetter().' desc')
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * 1 when the name starts with a letter, so names starting with a digit or a
     * symbol ("#1 Games", "[Bracket]") list after the alphabet rather than
     * before it. SQLite has no Unicode letter class, so there it is A to Z only.
     */
    private function startsWithLetter(): string
    {
        return match ((new Brand)->getConnection()->getDriverName()) {
            'mysql', 'mariadb' => "(name regexp '^[[:alpha:]]')",
            'pgsql' => "(case when name ~ '^[[:alpha:]]' then 1 else 0 end)",
            default => "(case when name glob '[A-Za-z]*' then 1 else 0 end)",
        };
    }
}
