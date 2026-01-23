<?php

namespace Lunar\Storefront\Facades;

use Illuminate\Support\Facades\Facade;
use Lunar\Storefront\Contracts\PropManager;

class Props extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PropManager::class;
    }
}
