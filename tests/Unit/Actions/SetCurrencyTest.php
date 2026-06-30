<?php

use Lunar\Core\Contracts\StorefrontSession;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Region;
use Lunar\Storefront\Actions\SetCurrency;

beforeEach(function () {
    $this->language = Language::factory()->create(['default' => true]);
    $this->channel = Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);
});

test('it sets currency by switching to a region with that currency', function () {
    $usd = Currency::factory()->create([
        'code' => 'USD',
        'default' => true,
    ]);

    Region::factory()->create([
        'default' => true,
        'channel_id' => $this->channel->id,
        'currency_id' => $usd->id,
        'language_id' => $this->language->id,
    ]);

    $eur = Currency::factory()->create([
        'code' => 'EUR',
        'default' => false,
    ]);

    Region::factory()->create([
        'default' => false,
        'channel_id' => $this->channel->id,
        'currency_id' => $eur->id,
        'language_id' => $this->language->id,
    ]);

    $action = new SetCurrency;
    $action->set('EUR');

    $context = app(StorefrontSession::class);

    expect($context->getCurrency()->code)->toBe('EUR');
});

test('it does nothing when code is null', function () {
    $usd = Currency::factory()->create([
        'code' => 'USD',
        'default' => true,
    ]);

    Region::factory()->create([
        'default' => true,
        'channel_id' => $this->channel->id,
        'currency_id' => $usd->id,
        'language_id' => $this->language->id,
    ]);

    $action = new SetCurrency;
    $action->set(null);

    $context = app(StorefrontSession::class);

    expect($context->getCurrency()->code)->toBe('USD');
});

test('it does nothing when currency code is not found', function () {
    $usd = Currency::factory()->create([
        'code' => 'USD',
        'default' => true,
    ]);

    Region::factory()->create([
        'default' => true,
        'channel_id' => $this->channel->id,
        'currency_id' => $usd->id,
        'language_id' => $this->language->id,
    ]);

    $action = new SetCurrency;
    $action->set('INVALID');

    $context = app(StorefrontSession::class);

    expect($context->getCurrency()->code)->toBe('USD');
});
