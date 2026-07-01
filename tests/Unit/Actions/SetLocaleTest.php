<?php

use Illuminate\Support\Facades\Session;
use Lunar\Storefront\Actions\SetLocale;

test('it sets locale in session', function () {
    $action = new SetLocale;

    $action->set('fr');

    expect(Session::get('locale'))->toBe('fr');
});

test('it can change locale', function () {
    $action = new SetLocale;

    $action->set('en');
    expect(Session::get('locale'))->toBe('en');

    $action->set('de');
    expect(Session::get('locale'))->toBe('de');
});

test('it accepts any locale string', function () {
    $action = new SetLocale;

    $action->set('en-GB');
    expect(Session::get('locale'))->toBe('en-GB');

    $action->set('zh-Hans');
    expect(Session::get('locale'))->toBe('zh-Hans');
});
