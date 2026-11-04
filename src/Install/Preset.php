<?php

namespace Lunar\Storefront\Install;

interface Preset
{
    /**
     * Seed the reference data for this preset. Safe to run more than once.
     */
    public function seed(): void;
}
