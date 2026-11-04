<?php

use Illuminate\Http\Request;
use Lunar\Storefront\Search\PriceFilter;

it('builds price ranges from the query string', function (array $query, ?string $expected) {
    expect(PriceFilter::fromRequest(Request::create('/', 'GET', $query)))->toBe($expected);
})->with([
    'both bounds' => [['price_min' => '10', 'price_max' => '50'], '[1000..5000]'],
    'min only' => [['price_min' => '10'], '>=1000'],
    'max only' => [['price_max' => '50'], '<=5000'],
    'reversed' => [['price_min' => '50', 'price_max' => '10'], '[1000..5000]'],
    'inc vat' => [['price_min' => '12', 'price_vat' => 'inc'], '>=1000'],
    'non numeric' => [['price_min' => 'abc', 'price_max' => '50'], '<=5000'],
    'negative' => [['price_min' => '-5'], '>=0'],
    'nothing' => [[], null],
]);

it('compares against VAT-inclusive stored prices when the store keeps them', function (array $query, string $expected) {
    config(['lunar.pricing.stored_inclusive_of_tax' => true]);

    expect(PriceFilter::fromRequest(Request::create('/', 'GET', $query)))->toBe($expected);
})->with([
    'inc vat bound used as is' => [['price_max' => '50', 'price_vat' => 'inc'], '<=5000'],
    'ex vat bound has VAT added' => [['price_max' => '50'], '<=6000'],
]);
