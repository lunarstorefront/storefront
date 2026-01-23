<?php

namespace Lunar\Storefront\Actions\Encryption;

use Illuminate\Support\Facades\Crypt;

class GetProductOptionsHash
{
    public function get(array $options): string
    {
        // Convert to compact format: "1:2,3:5"
        $pairs = [];
        foreach ($options as $optionId => $valueId) {
            $pairs[] = "{$optionId}:{$valueId}";
        }
        $data = implode(',', $pairs);

        // Create HMAC signature (prevents tampering)
        $signature = hash_hmac('sha256', $data, config('storefront.key'));

        // Combine and encode: "data.signature"
        $combined = base64_encode($data . '.' . substr($signature, 0, 16));

        // Make URL-safe
        return rtrim(strtr($combined, '+/', '-_'), '=');
    }
}
