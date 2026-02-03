<?php

namespace Lunar\Storefront\Data;

use Lunar\Base\Addressable;
use Spatie\LaravelData\Data;

/** @typescript */
class Address extends Data
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
        public ?string $postcode,
        public ?string $deliveryInstructions,
        public ?string $countryId,
        public ?string $contactEmail,
        public ?string $contactPhone,
        public ?string $countryIso,
        public ?string $countryName,
    ) {}

    public static function fromModel(Addressable $address): self
    {
        return new self(
            id: $address->id,
            title: $address->title,
            firstName: $address->first_name,
            lastName: $address->last_name,
            companyName: $address->company_name,
            lineOne: $address->line_one,
            lineTwo: $address->line_two,
            lineThree: $address->line_three,
            city: $address->city,
            state: $address->state,
            postcode: $address->postcode,
            deliveryInstructions: $address->delivery_instructions,
            countryId: $address->country_id,
            contactEmail: $address->contact_email,
            contactPhone: $address->contact_phone,
            countryIso: $address->country?->iso2,
            countryName: $address->country?->name,
        );
    }
}
