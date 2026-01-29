<?php

use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Storefront\Contracts\SearchManager as SearchManagerContract;
use Lunar\Storefront\Managers\SearchManager;

beforeEach(function () {
    $this->manager = new SearchManager();

    Language::factory()->create(['default' => true]);
    Currency::factory()->create(['default' => true]);
    Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);
});

test('it implements the search manager contract', function () {
    expect($this->manager)->toBeInstanceOf(SearchManagerContract::class);
});

test('it has a getResults method', function () {
    expect(method_exists($this->manager, 'getResults'))->toBeTrue();
});

test('it has an updateQuerySuggestion method', function () {
    expect(method_exists($this->manager, 'updateQuerySuggestion'))->toBeTrue();
});
