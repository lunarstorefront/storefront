<?php

namespace Lunar\Storefront\Install\Presets;

use Illuminate\Support\Facades\DB;
use Lunar\Storefront\Install\Preset;
use Lunar\Storefront\Install\Presets\Uk\SeedShipping;
use Lunar\Storefront\Install\Presets\Uk\SeedStoreSettings;

final class UkPreset implements Preset
{
    public function seed(): void
    {
        DB::transaction(function () {
            (new SeedStoreSettings)();
            (new SeedShipping)();
        });
    }
}
