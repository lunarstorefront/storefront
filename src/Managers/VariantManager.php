<?php

namespace Lunar\Storefront\Managers;

use Lunar\Models\Contracts\ProductVariant;

class VariantManager implements \Lunar\Storefront\Contracts\VariantManager
{
    protected string $key;

    public function __construct()
    {
        $this->key = config('storefront.key');
    }

    public function getBySku(string $sku): ?ProductVariant
    {
        return \Lunar\Models\ProductVariant::whereSku($sku)->first();
    }

    public function encryptOptions(array $options): string
    {
        // Create HMAC signature (prevents tampering)
        $signature = hash_hmac('sha256', serialize($options), $this->key);

        // Combine and encode: "data.signature"
        $combined = base64_encode(serialize($options) . '.' . substr($signature, 0, 16));

        // Make URL-safe
        return rtrim(strtr($combined, '+/', '-_'), '=');
    }

    public function decryptOptions(string $hash): array
    {
        try {
            $combined = base64_decode(strtr($hash, '-_', '+/'));

            $parts = explode('.', $combined);

            if (count($parts) !== 2) {
                return [];
            }

            $fullSig = hash_hmac('sha256', $parts[0], $this->key);
            if (!hash_equals(substr($fullSig, 0, 16), $parts[1])) {
                throw new \ErrorException('Tampered');
            }
        } catch (\Exception $e) {
            return [];
        }

        return unserialize($parts[0]);
    }
}
