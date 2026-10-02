<?php

namespace Lunar\Storefront\Tests\Stubs;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Lunar\Core\Contracts\LunarUser;
use Lunar\Core\Models\Concerns\IsLunarUser;

/** A storefront customer's login, as an app's User model would be. */
class User extends Authenticatable implements LunarUser
{
    use IsLunarUser;

    protected $guarded = [];

    protected $table = 'users';
}
