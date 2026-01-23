<?php

namespace Lunar\Storefront\Data\Traits;

use Illuminate\Support\Facades\Storage;
use Lunar\Base\FieldType;
use Lunar\FieldTypes\Dropdown;
use Lunar\FieldTypes\File;
use Lunar\Storefront\Data\AttributeDataValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait HasAttributeData
{
    public static function mapAttributes(Model $model): Collection
    {
        $attributeValues = collect();

        // TODO: How do we solve this for collections as attributes aren't directly related
        $mappedAttributes = $model->mappedAttributes();

        /**
         * Remove name, description
         */
        $attributes = collect($model->attribute_data)->reject(
            fn ($attribute, $handle) => in_array($handle, ['name', 'description'])
        );

        foreach ($attributes as $handle => $attributeValue) {
            $attribute = $mappedAttributes->firstWhere('handle', $handle);

            $value = $attributeValue->getValue();

            if ($attributeValue instanceof Dropdown) {
                $value = collect($attribute->configuration['lookups'] ?? [])->first(
                    fn ($lookup) => $lookup['value'] === $value
                )['label'] ?? $value;
            }

            if ($attributeValue instanceof File) {
                $value = collect($value)->map(
                  fn ($value) => Storage::url($value)
                );
            }

            $attributeValues->push(
                new AttributeDataValue(
                    name: $attribute?->translate('name') ?: $handle,
                    handle: $handle,
                    type: strtolower(class_basename($attributeValue)),
                    value: $value,
                )
            );
        }

        return $attributeValues->mapWithKeys(
            fn (AttributeDataValue $value) => [$value->handle => $value]
        );
    }
}
