<?php

namespace Lunar\Storefront\Contracts;

interface BrandManager
{
    public function getIndexPageProps(): array;

    public function getShowPageProps(string $slug, ?string $sort, int $perPage): array;
}
