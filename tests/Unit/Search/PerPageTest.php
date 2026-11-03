<?php

use Illuminate\Http\Request;
use Lunar\Storefront\Search\PerPage;

it('only accepts configured page sizes', function (mixed $value, int $expected) {
    expect(PerPage::fromRequest(Request::create('/', 'GET', ['per_page' => $value])))->toBe($expected);
})->with([
    'allowed' => [80, 80],
    'not allowed' => [99999, 40],
    'junk' => ['abc', 40],
]);

it('reads page sizes from config', function () {
    config(['storefront.search.per_page' => ['default' => 24, 'options' => [24, 48]]]);
    expect(PerPage::default())->toBe(24)->and(PerPage::options())->toBe([24, 48]);
});
