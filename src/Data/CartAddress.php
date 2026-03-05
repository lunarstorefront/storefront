<?php

namespace Lunar\Storefront\Data;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Data;

/** @typescript  */
class CartAddress extends Data
{
    public function __construct(
        public ?string $id,
        public ?string $title,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $companyName,
        public ?string $lineOne,
        public ?string $lineTwo,
        public ?string $lineThree,
        public ?string $city,
        public ?string $state,
        #[Max(8)]
        public ?string $postcode,
        public ?string $deliveryInstructions,
        public ?string $countryId,
        public ?string $countryIso,
        public ?string $countryName,
        #[Email]
        public ?string $contactEmail,
        public ?string $contactPhone,
        #[Nullable]
        public ?string $shippingOption,
    ) {}

    public static function fromModel(\Lunar\Models\Contracts\CartAddress $cartAddress): self
    {
        return new self(
            id: $cartAddress->id,
            title: $cartAddress->title,
            firstName: $cartAddress->first_name,
            lastName: $cartAddress->last_name,
            companyName: $cartAddress->company_name,
            lineOne: $cartAddress->line_one,
            lineTwo: $cartAddress->line_two,
            lineThree: $cartAddress->line_three,
            city: $cartAddress->city,
            state: $cartAddress->state,
            postcode: $cartAddress->postcode ?: '',
            deliveryInstructions: $cartAddress->delivery_instructions,
            countryId: $cartAddress->country?->id,
            countryIso: $cartAddress->country?->iso2,
            countryName: $cartAddress->country?->name,
            contactEmail: $cartAddress->contact_email,
            contactPhone: $cartAddress->contact_phone,
            shippingOption: $cartAddress->shipping_option,
        );
    }
}
