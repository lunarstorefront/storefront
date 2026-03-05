<?php

namespace Lunar\Storefront\Data;

use Spatie\LaravelData\Data;

/** @typescript  */
class CheckoutElement extends Data
{
    public function __construct(
        public string $title,
        public string $handle,
        public bool $isComplete,
        public bool $visible,
        public bool $enabled,
        public array $props,
        public array $data,
    ) {}

    protected static function getDataClassFromProp($prop): mixed
    {
        $prop = is_iterable($prop) ? collect($prop) : $prop;

        if (is_iterable($prop)) {
            $firstProp = $prop->first();

            return match (true) {
                $firstProp instanceof \Lunar\Models\Contracts\CartAddress => CartAddress::collect($prop),
                $firstProp instanceof \Lunar\Models\Contracts\Country => Country::collect($prop),
                $firstProp instanceof \Lunar\DataTypes\ShippingOption => $prop->map(
                    fn (\Lunar\DataTypes\ShippingOption $shippingOption) => ShippingOption::from([
                        'name' => $shippingOption->getName(),
                        'description' => $shippingOption->getDescription(),
                        'identifier' => $shippingOption->getIdentifier(),
                        'price' => $shippingOption->price,
                        'cutoff' => $shippingOption->meta['cutoff'] ?? null,
                    ])
                ),
                default => $prop
            };
        }

        return match (true) {
            $prop instanceof \Lunar\Models\Contracts\CartAddress => CartAddress::from($prop),
            $prop instanceof \Lunar\DataTypes\ShippingOption => ShippingOption::from($prop),
            default => $prop
        };
    }

    public static function fromArray(array $data): self
    {
        $props = [];

        foreach ($data['props'] ?? [] as $prop => $value) {
            $props[$prop] = static::getDataClassFromProp($value);
        }

        return new self(
            title: $data['title'],
            handle: $data['handle'],
            isComplete: $data['isComplete'],
            visible: $data['visible'],
            enabled: $data['enabled'],
            props: $props,
            data: $data['data'],
        );
    }
}
