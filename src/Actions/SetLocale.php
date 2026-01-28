<?php

namespace Lunar\Storefront\Actions;

use Illuminate\Support\Facades\Session;

class SetLocale
{
    public function set(string $locale): void
    {
        Session::put('locale', $locale);
    }
}
