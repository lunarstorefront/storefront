<?php

namespace Lunar\Storefront\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Lunar\Storefront\Contracts\PropManager;
use Lunar\Storefront\PropData;

/**
 * @method static array resolve(string $page, ?Model $record = null)
 * @method static void add(array|Collection|PropData $propData)
 *
 * @see \Lunar\Storefront\Managers\PropManager
 */
class Props extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PropManager::class;
    }
}
