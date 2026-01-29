<?php

use Lunar\Storefront\Actions\Encryption\GetProductOptionsHash;
use Lunar\Storefront\Managers\VariantManager;

test('it produces url-safe hash', function () {
    config(['storefront.key' => 'test-secret-key']);

    $action = new GetProductOptionsHash();
    $hash = $action->get(['1' => '2', '3' => '5']);

    expect($hash)
        ->toBeString()
        ->not->toContain('+')
        ->not->toContain('/')
        ->not->toContain('=');
});

test('it produces consistent hashes for same input', function () {
    config(['storefront.key' => 'test-secret-key']);

    $action = new GetProductOptionsHash();
    $options = ['color' => '1', 'size' => '2'];

    $hash1 = $action->get($options);
    $hash2 = $action->get($options);

    expect($hash1)->toBe($hash2);
});

test('it produces different hashes for different inputs', function () {
    config(['storefront.key' => 'test-secret-key']);

    $action = new GetProductOptionsHash();

    $hash1 = $action->get(['1' => '2']);
    $hash2 = $action->get(['1' => '3']);

    expect($hash1)->not->toBe($hash2);
});

/**
 * ISSUE: GetProductOptionsHash uses config('storefront.key') but VariantManager uses Crypt::getKey()
 * This means hashes created by GetProductOptionsHash cannot be decrypted by VariantManager
 */
test('hashes are incompatible with VariantManager decryption', function () {
    config(['storefront.key' => 'test-secret-key']);
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

    $options = ['1' => '2', '3' => '5'];

    // Create hash using GetProductOptionsHash
    $hashAction = new GetProductOptionsHash();
    $hash = $hashAction->get($options);

    // Try to decrypt using VariantManager
    $variantManager = new VariantManager();
    $decrypted = $variantManager->decryptOptions($hash);

    // This will fail because they use different keys/formats
    expect($decrypted)->not->toBe($options)
        ->and($decrypted)->toBeEmpty();
})->skip('Known issue: GetProductOptionsHash appears to be unused legacy code with incompatible encryption');

/**
 * Additional concern: config('storefront.key') is likely not set in most installations
 */
test('it fails when storefront.key is not configured', function () {
    config(['storefront.key' => null]);

    $action = new GetProductOptionsHash();

    // hash_hmac with null key will use empty string, producing weak signatures
    $hash = $action->get(['1' => '2']);

    // This "works" but is insecure
    expect($hash)->toBeString();
})->skip('Security concern: This class should validate that the key is configured');
