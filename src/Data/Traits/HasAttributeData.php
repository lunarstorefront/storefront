<?php

namespace Lunar\Storefront\Data\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lunar\Core\FieldTypes\Dropdown;
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

        foreach ($attributes as $handle => $attributeValue) {
            $value = $attributeValue->getValue();

            if ($attributeValue instanceof Dropdown) {
                $value = $value;
            }

            $attributeValues->push(
                new AttributeDataValue(
                    name: $handle,
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
}
