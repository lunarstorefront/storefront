<?php

namespace Lunar\Storefront\Data;

use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * @phpstan-consistent-constructor
 */
#[TypeScript]
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
        public ?string $countryId,
        public ?string $contactEmail,
        public ?string $contactPhone,
        public ?string $countryIso,
        public ?string $countryName,
    ) {}

    public static function fromModel(Model $address): static
    {
        return new static(
            id: $address->id === null ? null : (string) $address->id,
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
            countryId: $address->country_id === null ? null : (string) $address->country_id,
            contactEmail: $address->contact_email,
            contactPhone: $address->contact_phone,
            countryIso: $address->country?->iso2,
            countryName: $address->country?->name,
        );
    }
}
