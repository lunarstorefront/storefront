<?php

use Illuminate\Support\Facades\Schema;
use Lunar\Shipping\Models\ShippingZone;

it('migrates the table-rate shipping tables', function () {
    expect(Schema::hasTable((new ShippingZone)->getTable()))->toBeTrue();
});
