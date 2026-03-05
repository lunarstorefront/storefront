<?php

use Lunar\Storefront\Managers\PropManager;
use Lunar\Storefront\PropData;
use Lunar\Storefront\StorefrontPage;

beforeEach(function () {
    $this->manager = new PropManager();
});

test('it can add a single prop', function () {
    $prop = new PropData(
        page: StorefrontPage::ProductsShow,
        key: 'testKey',
        callback: fn () => 'testValue',
    );

    $this->manager->add($prop);

    $resolved = $this->manager->resolve(StorefrontPage::ProductsShow);

    expect($resolved)->toHaveKey('testKey')
        ->and($resolved['testKey'])->toBe('testValue');
});

test('it can add multiple props as an array', function () {
    $props = [
        new PropData(
            page: StorefrontPage::ProductsShow,
            key: 'key1',
            callback: fn () => 'value1',
        ),
        new PropData(
            page: StorefrontPage::ProductsShow,
            key: 'key2',
            callback: fn () => 'value2',
        ),
    ];

    $this->manager->add($props);

    $resolved = $this->manager->resolve(StorefrontPage::ProductsShow);

    expect($resolved)
        ->toHaveKey('key1')
        ->toHaveKey('key2')
        ->and($resolved['key1'])->toBe('value1')
        ->and($resolved['key2'])->toBe('value2');
});

test('it can add props from a collection', function () {
    $props = collect([
        new PropData(
            page: StorefrontPage::CollectionsShow,
            key: 'collectionKey',
            callback: fn () => 'collectionValue',
        ),
    ]);

    $this->manager->add($props);

    $resolved = $this->manager->resolve(StorefrontPage::CollectionsShow);

    expect($resolved)->toHaveKey('collectionKey')
        ->and($resolved['collectionKey'])->toBe('collectionValue');
});

test('it only resolves props for the specified page', function () {
    $this->manager->add([
        new PropData(
            page: StorefrontPage::ProductsShow,
            key: 'productKey',
            callback: fn () => 'productValue',
        ),
        new PropData(
            page: StorefrontPage::CollectionsShow,
            key: 'collectionKey',
            callback: fn () => 'collectionValue',
        ),
    ]);

    $productProps = $this->manager->resolve(StorefrontPage::ProductsShow);
    $collectionProps = $this->manager->resolve(StorefrontPage::CollectionsShow);

    expect($productProps)
        ->toHaveKey('productKey')
        ->not->toHaveKey('collectionKey')
        ->and($collectionProps)
        ->toHaveKey('collectionKey')
        ->not->toHaveKey('productKey');
});

test('it passes model record to callback', function () {
    $model = new class extends \Illuminate\Database\Eloquent\Model {
        public string $name = 'TestModel';
    };

    $this->manager->add(new PropData(
        page: StorefrontPage::ProductsShow,
        key: 'modelName',
        callback: fn ($record) => $record?->name,
    ));

    $resolved = $this->manager->resolve(StorefrontPage::ProductsShow, $model);

    expect($resolved['modelName'])->toBe('TestModel');
});

test('it returns empty array when no props match the page', function () {
    $this->manager->add(new PropData(
        page: StorefrontPage::ProductsShow,
        key: 'key',
        callback: fn () => 'value',
    ));

    $resolved = $this->manager->resolve(StorefrontPage::SearchIndex);

    expect($resolved)->toBeEmpty();
});

test('it can use a class string as callback', function () {
    $this->manager->add(new PropData(
        page: StorefrontPage::ProductsShow,
        key: 'invokable',
        callback: InvokableTestClass::class,
    ));

    $resolved = $this->manager->resolve(StorefrontPage::ProductsShow);

    expect($resolved['invokable'])->toBe('invoked');
});

test('resolve accepts a plain string for backward compatibility', function () {
    $this->manager->add(new PropData(
        page: StorefrontPage::ProductsShow,
        key: 'key',
        callback: fn () => 'value',
    ));

    $resolved = $this->manager->resolve('products.show');

    expect($resolved)->toHaveKey('key')
        ->and($resolved['key'])->toBe('value');
});

test('PropData registered with a plain string resolves via enum', function () {
    $this->manager->add(new PropData(
        page: 'products.show',
        key: 'key',
        callback: fn () => 'value',
    ));

    $resolved = $this->manager->resolve(StorefrontPage::ProductsShow);

    expect($resolved)->toHaveKey('key')
        ->and($resolved['key'])->toBe('value');
});

test('PropData::getPage returns the string value of an enum', function () {
    $prop = new PropData(
        page: StorefrontPage::ProductsShow,
        key: 'key',
        callback: fn () => 'value',
    );

    expect($prop->getPage())->toBe('products.show');
});

test('PropData::getPage returns the string unchanged when not an enum', function () {
    $prop = new PropData(
        page: 'custom.page',
        key: 'key',
        callback: fn () => 'value',
    );

    expect($prop->getPage())->toBe('custom.page');
});

class InvokableTestClass
{
    public function __invoke($record = null): string
    {
        return 'invoked';
    }
}
