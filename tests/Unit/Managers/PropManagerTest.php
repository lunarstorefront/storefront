<?php

use Lunar\Storefront\Managers\PropManager;
use Lunar\Storefront\PropData;

beforeEach(function () {
    $this->manager = new PropManager();
});

test('it can add a single prop', function () {
    $prop = new PropData(
        page: 'products.show',
        key: 'testKey',
        callback: fn () => 'testValue',
    );

    $this->manager->add($prop);

    $resolved = $this->manager->resolve('products.show');

    expect($resolved)->toHaveKey('testKey')
        ->and($resolved['testKey'])->toBe('testValue');
});

test('it can add multiple props as an array', function () {
    $props = [
        new PropData(
            page: 'products.show',
            key: 'key1',
            callback: fn () => 'value1',
        ),
        new PropData(
            page: 'products.show',
            key: 'key2',
            callback: fn () => 'value2',
        ),
    ];

    $this->manager->add($props);

    $resolved = $this->manager->resolve('products.show');

    expect($resolved)
        ->toHaveKey('key1')
        ->toHaveKey('key2')
        ->and($resolved['key1'])->toBe('value1')
        ->and($resolved['key2'])->toBe('value2');
});

test('it can add props from a collection', function () {
    $props = collect([
        new PropData(
            page: 'collections.show',
            key: 'collectionKey',
            callback: fn () => 'collectionValue',
        ),
    ]);

    $this->manager->add($props);

    $resolved = $this->manager->resolve('collections.show');

    expect($resolved)->toHaveKey('collectionKey')
        ->and($resolved['collectionKey'])->toBe('collectionValue');
});

test('it only resolves props for the specified page', function () {
    $this->manager->add([
        new PropData(
            page: 'products.show',
            key: 'productKey',
            callback: fn () => 'productValue',
        ),
        new PropData(
            page: 'collections.show',
            key: 'collectionKey',
            callback: fn () => 'collectionValue',
        ),
    ]);

    $productProps = $this->manager->resolve('products.show');
    $collectionProps = $this->manager->resolve('collections.show');

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
        page: 'test.page',
        key: 'modelName',
        callback: fn ($record) => $record?->name,
    ));

    $resolved = $this->manager->resolve('test.page', $model);

    expect($resolved['modelName'])->toBe('TestModel');
});

test('it returns empty array when no props match the page', function () {
    $this->manager->add(new PropData(
        page: 'products.show',
        key: 'key',
        callback: fn () => 'value',
    ));

    $resolved = $this->manager->resolve('non.existent.page');

    expect($resolved)->toBeEmpty();
});

test('it can use a class string as callback', function () {
    $this->manager->add(new PropData(
        page: 'test.page',
        key: 'invokable',
        callback: InvokableTestClass::class,
    ));

    $resolved = $this->manager->resolve('test.page');

    expect($resolved['invokable'])->toBe('invoked');
});

class InvokableTestClass
{
    public function __invoke($record = null): string
    {
        return 'invoked';
    }
}
