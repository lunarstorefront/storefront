<?php

use Lunar\Storefront\Managers\VariantManager;

beforeEach(function () {
    $this->manager = new VariantManager();
});

test('it can encrypt options array', function () {
    $options = ['color' => 'red', 'size' => 'large'];

    $encrypted = $this->manager->encryptOptions($options);

    expect($encrypted)
        ->toBeString()
        ->not->toContain('+')
        ->not->toContain('/')
        ->not->toContain('=');
});

test('it can decrypt options back to original array', function () {
    $options = ['color' => 'blue', 'size' => 'medium'];

    $encrypted = $this->manager->encryptOptions($options);
    $decrypted = $this->manager->decryptOptions($encrypted);

    expect($decrypted)->toBe($options);
});

test('it returns empty array when decrypting null', function () {
    $decrypted = $this->manager->decryptOptions(null);

    expect($decrypted)->toBeEmpty();
});

test('it returns empty array when decrypting invalid hash', function () {
    $decrypted = $this->manager->decryptOptions('invalid-hash');

    expect($decrypted)->toBeEmpty();
});

test('it returns empty array when hash has been tampered with', function () {
    $options = ['color' => 'green'];
    $encrypted = $this->manager->encryptOptions($options);

    $tampered = substr($encrypted, 0, -5) . 'xxxxx';
    $decrypted = $this->manager->decryptOptions($tampered);

    expect($decrypted)->toBeEmpty();
});

test('it produces url-safe encrypted strings', function () {
    $options = [
        'option1' => 'value with spaces',
        'option2' => 'special+chars/here=now',
    ];

    $encrypted = $this->manager->encryptOptions($options);

    expect($encrypted)->toMatch('/^[A-Za-z0-9_-]+$/');
});

test('it encrypts same options to same hash', function () {
    $options = ['key' => 'value'];

    $hash1 = $this->manager->encryptOptions($options);
    $hash2 = $this->manager->encryptOptions($options);

    expect($hash1)->toBe($hash2);
});

test('it encrypts different options to different hashes', function () {
    $options1 = ['key' => 'value1'];
    $options2 = ['key' => 'value2'];

    $hash1 = $this->manager->encryptOptions($options1);
    $hash2 = $this->manager->encryptOptions($options2);

    expect($hash1)->not->toBe($hash2);
});

test('it can get variant by sku', function () {
    $currency = \Lunar\Kernel\Models\Currency::factory()->create(['default' => true]);
    $channel = \Lunar\Kernel\Models\Channel::factory()->create(['default' => true]);
    $language = \Lunar\Kernel\Models\Language::factory()->create(['default' => true]);
    $customerGroup = \Lunar\Kernel\Models\CustomerGroup::factory()->create(['default' => true]);
    $taxClass = \Lunar\Kernel\Models\TaxClass::factory()->create(['default' => true]);
    $productType = \Lunar\Catalog\Models\ProductType::factory()->create();

    $product = \Lunar\Catalog\Models\Product::factory()
        ->for($productType)
        ->create();

    $variant = \Lunar\Catalog\Models\ProductVariant::factory()
        ->for($product)
        ->for($taxClass)
        ->create(['sku' => 'TEST-SKU-123']);

    $found = $this->manager->getBySku('TEST-SKU-123');

    expect($found)->not->toBeNull()
        ->and($found->sku)->toBe('TEST-SKU-123');
});

test('it returns null for non-existent sku', function () {
    $found = $this->manager->getBySku('NON-EXISTENT-SKU');

    expect($found)->toBeNull();
});
