<?php

use Lunar\Storefront\Shipping\UkPostcodes;

it('keeps highlands, islands and excluded areas out of mainland', function () {
    expect(UkPostcodes::mainland())
        ->toContain('AB', 'PA', 'B', 'EC')
        ->not->toContain('HS', 'IV', 'KW', 'ZE', 'BT', 'IM', 'JE', 'GY');
});

it('expands highlands and islands districts', function () {
    expect(UkPostcodes::highlandsAndIslands())
        ->toContain('HS', 'ZE', 'AB36', 'AB38', 'PA20', 'PA49', 'PA60', 'PA78', 'PO41', 'TR25')
        ->not->toContain('PA19', 'PA50', 'AB39');
});

it('lists the excluded areas', function () {
    expect(UkPostcodes::excluded())->toBe(['BT', 'IM', 'JE', 'GY']);
});

it('has no duplicate postcodes across zones', function () {
    $all = [...UkPostcodes::mainland(), ...UkPostcodes::highlandsAndIslands(), ...UkPostcodes::excluded()];
    expect($all)->toHaveCount(count(array_unique($all)));
});
