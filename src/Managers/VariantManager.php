<?php

namespace Lunar\Storefront\Managers;

use Illuminate\Support\Facades\Crypt;
use Lunar\Models\Contracts\Product;
use Lunar\Models\Contracts\ProductVariant;
use Lunar\Storefront\Actions\Catalog\GetProductVariantByProvidedOptions;

class VariantManager implements \Lunar\Storefront\Contracts\VariantManager
{
    public function getBySku(string $sku): ?ProductVariant
    {
        return \Lunar\Models\ProductVariant::whereSku($sku)->first();
    }

    public function encryptOptions(array $options): string
    {
        ksort($options);

        $payload = collect($options)
            ->map(fn ($value, $key) => $key.':'.$value)
            ->implode(',');

        $signature = hash_hmac('sha256', $payload, Crypt::getKey(), binary: true);

        $combined = $payload.'.'.substr($signature, 0, 16);

        return rtrim(strtr(base64_encode($combined), '+/', '-_'), '=');
    }

    public function decryptOptions(?string $hash = null): array
    {
        if (! $hash) {
            return [];
        }

        try {
            $combined = base64_decode(strtr($hash, '-_', '+/'));

            $dotPosition = strrpos($combined, '.');

            if ($dotPosition === false) {
                return [];
            }

            $payload = substr($combined, 0, $dotPosition);
            $signature = substr($combined, $dotPosition + 1);

            $expectedSignature = hash_hmac('sha256', $payload, Crypt::getKey(), binary: true);

            if (! hash_equals(substr($expectedSignature, 0, 16), $signature)) {
                return [];
            }

            $options = [];

            foreach (explode(',', $payload) as $pair) {
                [$key, $value] = explode(':', $pair, 2);
                $options[$key] = $value;
            }

            return $options;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getSelectedOptions(?string $hash = null): \Illuminate\Support\Collection
    {
        return collect($this->decryptOptions($hash))->mapWithKeys(
            fn ($value, $option) => [$option => $value]
        );
    }

    public function getProvidedVariant(Product $product, ?string $hash = null): ?ProductVariant
    {
        if (! $hash) {
            return $product->variants->first();
        }

        return (new GetProductVariantByProvidedOptions)->get($product, $hash);
    }
}
