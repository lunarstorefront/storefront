<?php

namespace Lunar\Storefront\Install\Presets\Uk;

use Lunar\Core\Models\Country;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Shipping\Models\ShippingMethod;
use Lunar\Shipping\Models\ShippingRate;
use Lunar\Shipping\Models\ShippingZone;
use Lunar\Storefront\Shipping\UkPostcodes;

final class SeedShipping
{
    public function __invoke(): void
    {
        $gb = Country::where('iso2', 'GB')->sole();
        $currency = Currency::where('code', 'GBP')->sole();

        $zones = [
            'United Kingdom' => $this->seedZone('United Kingdom', 'countries', $gb, []),
            'Mainland' => $this->seedZone('Mainland', 'postcodes', $gb, UkPostcodes::mainland()),
            'Highlands and Islands' => $this->seedZone('Highlands and Islands', 'postcodes', $gb, UkPostcodes::highlandsAndIslands()),
            'Excluded' => $this->seedZone('Excluded', 'postcodes', $gb, UkPostcodes::excluded()),
        ];

        $methods = [
            ['standard', 'Standard Delivery', 'flat-rate', ['Mainland' => 695, 'Highlands and Islands' => 2495]],
            ['next-day', 'Next Day Delivery', 'flat-rate', ['Mainland' => 1295]],
            ['collection', 'Collection', 'collection', ['United Kingdom' => null]],
        ];

        $groups = CustomerGroup::pluck('id');

        foreach ($methods as [$code, $name, $driver, $rates]) {
            $method = ShippingMethod::firstOrCreate(['code' => $code], ['name' => $name, 'driver' => $driver]);

            if ($method->wasRecentlyCreated) {
                $method->customerGroups()->syncWithoutDetaching(
                    $groups->mapWithKeys(fn (int $id) => [$id => ['enabled' => true, 'visible' => true]])->all()
                );
            }

            foreach ($rates as $zoneName => $amount) {
                $rate = ShippingRate::firstOrCreate([
                    'shipping_method_id' => $method->id,
                    'shipping_zone_id' => $zones[$zoneName]->id,
                ]);

                if ($rate->wasRecentlyCreated && $amount !== null) {
                    $rate->prices()->create([
                        'price' => $amount,
                        'currency_id' => $currency->id,
                        'min_quantity' => 1,
                    ]);
                }
            }
        }
    }

    /**
     * @param  list<string>  $postcodes
     */
    private function seedZone(string $name, string $type, Country $gb, array $postcodes): ShippingZone
    {
        $zone = ShippingZone::firstOrCreate(['name' => $name], ['type' => $type]);

        if ($zone->wasRecentlyCreated) {
            $zone->countries()->attach($gb->id);

            foreach ($postcodes as $postcode) {
                $zone->postcodes()->create(['postcode' => $postcode]);
            }
        }

        return $zone;
    }
}
