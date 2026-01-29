<?php

namespace Lunar\Storefront\Contracts;

interface StorefrontManager
{
    public function variants(): VariantManager;

    public function products(): ProductManager;

    public function collections(): CollectionManager;

    public function search(): SearchManager;

    public function pricing(): PricingManager;

    public function setCurrency(string $code): void;

    public function setLocale(string $locale): void;
}
