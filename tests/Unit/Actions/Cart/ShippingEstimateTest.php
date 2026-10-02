<?php

use Lunar\Core\DataObjects\PriceValue;
use Lunar\Core\DataTypes\ShippingOption as ShippingOptionValue;
use Lunar\Core\Facades\ShippingManifest;
use Lunar\Core\Models\Cart;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Country;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\TaxClass;
use Lunar\Core\Modifiers\ShippingModifier;
use Lunar\Core\Modifiers\ShippingModifiers;
use Lunar\Storefront\Actions\Cart\EstimateShipping;
use Lunar\Storefront\Actions\Cart\SetShippingPostcode;
use Lunar\Storefront\Data\ShippingOption;

/**
 * Stands in for a shipping package: standard and next day everywhere except
 * BT postcodes, and collection always.
 */
class EstimateTestShippingModifier extends ShippingModifier
{
    public function handle(Cart $cart, Closure $next)
    {
        $taxClass = TaxClass::query()->first();
        $currency = $cart->currency;
        $option = fn (string $name, string $identifier, int $price, bool $collect = false) => new ShippingOptionValue(
            name: $name,
            description: "{$name} description",
            identifier: $identifier,
            price: new PriceValue($price, $currency),
            taxClass: $taxClass,
            collect: $collect,
        );

        if (! str_starts_with((string) $cart->shippingAddress?->postcode, 'BT')) {
            ShippingManifest::addOption($option('Next day', 'NEXT_DAY', 1500));
            ShippingManifest::addOption($option('Standard delivery', 'STANDARD', 695));
        }

        ShippingManifest::addOption($option('Click & collect', 'COLLECT', 0, collect: true));

        return $next($cart);
    }
}

beforeEach(function () {
    Currency::factory()->create(['default' => true]);
    Channel::factory()->create(['default' => true]);
    CustomerGroup::factory()->create(['default' => true]);
    TaxClass::factory()->create(['default' => true]);
    $this->country = Country::factory()->create(['iso2' => 'GB']);

    app(ShippingModifiers::class)->add(EstimateTestShippingModifier::class);
});

function estimateCart(): Cart
{
    return Cart::factory()->create([
        'currency_id' => Currency::getDefault()->id,
        'channel_id' => Channel::getDefault()->id,
    ]);
}

test('it keeps a postcode on a cart that has no delivery address', function () {
    $cart = (new SetShippingPostcode)->set(estimateCart(), 'bl9 0sa', $this->country);

    $address = $cart->refresh()->shippingAddress;

    expect($address->postcode)->toBe('BL9 0SA')
        ->and($address->country_id)->toBe($this->country->id)
        ->and($address->line_one)->toBeNull();
});

test('it leaves a full delivery address alone when the postcode is the same', function () {
    $cart = estimateCart();
    $cart->setShippingAddress([
        'first_name' => 'Sam',
        'line_one' => '1 Site Road',
        'city' => 'Bury',
        'postcode' => 'BL9 0SA',
        'country_id' => $this->country->id,
    ]);

    (new SetShippingPostcode)->set($cart->refresh(), 'bl90sa', $this->country);

    expect($cart->refresh()->shippingAddress->line_one)->toBe('1 Site Road');
});

test('a different postcode replaces the delivery address', function () {
    $cart = estimateCart();
    $cart->setShippingAddress([
        'first_name' => 'Sam',
        'line_one' => '1 Site Road',
        'city' => 'Bury',
        'postcode' => 'BL9 0SA',
        'country_id' => $this->country->id,
    ]);

    (new SetShippingPostcode)->set($cart->refresh(), 'M1 1AA', $this->country);

    $address = $cart->refresh()->shippingAddress;

    expect($address->postcode)->toBe('M1 1AA')
        ->and($address->line_one)->toBeNull();
});

test('it estimates the cheapest delivery option, never collection', function () {
    $cart = (new SetShippingPostcode)->set(estimateCart(), 'BL9 0SA', $this->country);

    $estimate = (new EstimateShipping)->get($cart->refresh());

    expect($estimate)->toBeInstanceOf(ShippingOption::class)
        ->and($estimate->name)->toBe('Standard delivery')
        ->and($estimate->identifier)->toBe('STANDARD')
        ->and($estimate->price)->toBe(695);
});

test('there is no estimate where nothing but collection is offered', function () {
    $cart = (new SetShippingPostcode)->set(estimateCart(), 'BT1 1AA', $this->country);

    expect((new EstimateShipping)->get($cart->refresh()))->toBeNull();
});

test('there is no estimate before a postcode is given', function () {
    expect((new EstimateShipping)->get(estimateCart()))->toBeNull();
});
