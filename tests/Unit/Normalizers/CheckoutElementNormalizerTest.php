<?php

use Lunar\Storefront\Normalizers\CheckoutElementNormalizer;

test('it normalizes an object with toArray method', function () {
    $normalizer = new CheckoutElementNormalizer();

    $object = new class
    {
        public function toArray(): array
        {
            return ['key' => 'value', 'nested' => ['a' => 1]];
        }
    };

    $result = $normalizer->normalize($object);

    expect($result)->toBe(['key' => 'value', 'nested' => ['a' => 1]]);
});
