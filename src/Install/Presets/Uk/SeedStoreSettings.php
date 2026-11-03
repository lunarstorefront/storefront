<?php

namespace Lunar\Storefront\Install\Presets\Uk;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lunar\Core\Enums\FieldTypeEnum;
use Lunar\Core\Models\Attribute;
use Lunar\Core\Models\AttributeGroup;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\CollectionGroup;
use Lunar\Core\Models\Country;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Location;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductType;
use Lunar\Core\Models\Region;
use Lunar\Core\Models\TaxClass;
use Lunar\Core\Models\TaxRate;
use Lunar\Core\Models\TaxZone;
use RuntimeException;
use Spatie\LaravelBlink\BlinkFacade as Blink;

final class SeedStoreSettings
{
    public function __invoke(): void
    {
        $gb = Country::where('iso2', 'GB')->first();

        if (! $gb) {
            throw new RuntimeException('Import countries before seeding the uk preset (php artisan lunar:import:address-data).');
        }

        $this->seedDefaults();

        $classes = [];
        foreach (['Standard' => true, 'Reduced' => false, 'Zero' => false] as $name => $default) {
            $classes[$name] = TaxClass::firstOrCreate(['name' => $name], ['default' => $default]);
        }
        Blink::forget('lunar_default_'.Str::snake(TaxClass::class));

        $uk = $this->seedZone('UK', true, [$gb->id], 'VAT', ['Standard' => 20, 'Reduced' => 5, 'Zero' => 0], $classes);
        $this->seedZone(
            'Rest of World',
            false,
            Country::where('id', '!=', $gb->id)->pluck('id')->all(),
            'No VAT',
            ['Standard' => 0, 'Reduced' => 0, 'Zero' => 0],
            $classes,
        );

        $this->seedRegion($uk);
        $this->seedCatalogue($classes['Standard']);
    }

    private function seedDefaults(): void
    {
        $this->createDefault(Channel::class, ['handle' => 'webstore'], [
            'name' => 'Webstore',
            'url' => config('app.url'),
        ]);
        $this->createDefault(Language::class, ['code' => 'en'], ['name' => 'English']);
        $this->createDefault(Currency::class, ['code' => 'GBP'], [
            'name' => 'British Pound',
            'exchange_rate' => 1,
            'decimal_places' => 2,
            'enabled' => true,
        ]);
        $this->createDefault(CustomerGroup::class, ['handle' => 'retail'], ['name' => 'Retail']);
        CustomerGroup::firstOrCreate(['handle' => 'trade'], ['name' => 'Trade', 'default' => false]);
        $this->createDefault(Location::class, ['handle' => 'warehouse'], ['name' => 'Warehouse']);
    }

    /**
     * Create the seeded row as the default. Other defaults are cleared only
     * when the row is created, so a second run never moves the default.
     *
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $key
     * @param  array<string, mixed>  $values
     */
    private function createDefault(string $model, array $key, array $values): Model
    {
        $existing = $model::where($key)->first();

        if ($existing) {
            return $existing;
        }

        $model::where('default', true)->update(['default' => false]);

        $created = $model::create([...$key, ...$values, 'default' => true]);

        Blink::forget('lunar_default_'.Str::snake($model));

        return $created;
    }

    /**
     * @param  array<int, int>  $countryIds
     * @param  array<string, int|float>  $percentages
     * @param  array<string, TaxClass>  $classes
     */
    private function seedZone(string $name, bool $default, array $countryIds, string $rateName, array $percentages, array $classes): TaxZone
    {
        $zone = TaxZone::firstOrCreate(['name' => $name], [
            'zone_type' => 'country',
            'active' => true,
            'default' => $default,
        ]);

        if ($zone->wasRecentlyCreated) {
            foreach ($countryIds as $countryId) {
                $zone->countries()->create(['country_id' => $countryId]);
            }
        }

        $rate = TaxRate::firstOrCreate(
            ['tax_zone_id' => $zone->id, 'name' => $rateName],
            ['priority' => 1],
        );

        if ($rate->wasRecentlyCreated) {
            foreach ($percentages as $className => $percentage) {
                $rate->taxRateAmounts()->create([
                    'tax_class_id' => $classes[$className]->id,
                    'percentage' => $percentage,
                ]);
            }
        }

        Blink::forget('lunar_default_'.Str::snake(TaxZone::class));

        return $zone;
    }

    private function seedRegion(TaxZone $uk): void
    {
        $this->createDefault(Region::class, ['handle' => 'united-kingdom'], [
            'name' => 'United Kingdom',
            'channel_id' => Channel::where('default', true)->value('id'),
            'currency_id' => Currency::where('default', true)->value('id'),
            'language_id' => Language::where('default', true)->value('id'),
            'tax_zone_id' => $uk->id,
        ]);
    }

    private function seedCatalogue(TaxClass $standard): void
    {
        CollectionGroup::firstOrCreate(['handle' => 'main'], ['name' => 'Main navigation']);

        $group = AttributeGroup::firstOrCreate(['handle' => 'details'], ['name' => 'Details', 'position' => 1]);

        $type = ProductType::firstOrCreate(['handle' => 'general'], [
            'name' => 'General',
            'default_tax_class_id' => $standard->id,
        ]);

        foreach (['name' => ['Name', true], 'description' => ['Description', false]] as $handle => [$label, $required]) {
            $attribute = Attribute::firstOrCreate(['handle' => $handle], [
                'attribute_group_id' => $group->id,
                'name' => $label,
                'type' => FieldTypeEnum::TranslatedText->value,
                'configuration' => [],
                'required' => $required,
                'searchable' => true,
                'position' => $required ? 1 : 2,
            ]);

            if ($attribute->wasRecentlyCreated) {
                $attribute->models()->create(['model_type' => (new Product)->getMorphClass()]);
            }

            if ($type->wasRecentlyCreated) {
                $type->attributeMapping()->attach($attribute->id);
            }
        }
    }
}
