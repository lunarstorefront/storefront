<?php

namespace Lunar\Storefront\Data\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lunar\Core\FieldTypes\Dropdown;
use Lunar\Core\Models\Attribute;
use Lunar\Storefront\Data\AttributeDataValue;

trait HasAttributeData
{
    public static function mapAttributes(Model $model): Collection
    {
        $attributeValues = collect();

        /**
         * Remove name, description
         */
        $attributes = collect($model->attribute_data)->reject(
            fn ($attribute, $handle) => in_array($handle, ['name', 'description'])
        );

        $names = $attributes->isEmpty() ? collect() : static::attributeNames($model->getMorphClass());

        foreach ($attributes as $handle => $attributeValue) {
            $value = $attributeValue->getValue();

            if ($attributeValue instanceof Dropdown) {
                $value = $value;
            }

            $attributeValues->push(
                new AttributeDataValue(
                    name: (string) ($names[$handle] ?? $handle),
                    handle: $handle,
                    type: strtolower(class_basename($attributeValue)),
                    value: $value,
                )
            );
        }

        // Keep this a sequential list — keying by handle makes the collection
        // serialize as a JSON object when non-empty but an array when empty,
        // breaking the AttributeDataValue[] contract on the frontend.
        return $attributeValues->values();
    }

    /**
     * The display name each handle is shown under, from the attributes the model type has.
     * Looked up once per type for the request, not once per model in a list.
     *
     * @return Collection<string, mixed>
     */
    protected static function attributeNames(string $morphClass): Collection
    {
        return once(fn () => Attribute::query()
            ->whereHas('models', fn ($query) => $query->where('model_type', $morphClass))
            ->pluck('name', 'handle'));
    }
}
