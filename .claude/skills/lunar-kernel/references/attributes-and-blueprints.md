# Attributes and Attribute Groups Reference

## Overview

Attributes define dynamic fields on Lunar models. The system is built around three concepts:

1. **AttributeGroup** — purely organisational; groups attributes by name and handle, no model-type coupling
2. **Attribute** — belongs to one group via direct FK; declares which models it applies to via `attribute_models`
3. **Attributables** — a polymorphic pivot that connects specific entities (e.g. a ProductType) to the attributes they use

---

## AttributeGroup

### Model: `Lunar\Kernel\Models\AttributeGroup`

Groups are purely organisational. They have no `attributable_type` — the same "SEO" group can hold attributes for Products, Brands, and Collections simultaneously.

### Fields

- `name` (string) — display name
- `handle` (string) — unique, auto-slugified
- `position` (integer, default 1) — display order
- `system` (boolean) — system groups cannot be deleted

### Relationships

```php
$group->attributes();  // HasMany Attribute ordered by position
```

### Usage

```php
$group = AttributeGroup::create(['name' => 'Details', 'handle' => 'details', 'position' => 1]);

// Attributes belong to one group via direct FK
$attribute->attribute_group_id = $group->id;
$attribute->save();

// Load a group with its attributes
$group->load('attributes');
```

---

## Attribute

### Model: `Lunar\Kernel\Models\Attribute`

### Fields

- `attribute_group_id` (FK → AttributeGroup, nullable)
- `name`, `handle` (string, unique — auto-slugified)
- `type` (string — field type class key)
- `position` (integer, default 1)
- `required` (boolean, default false)
- `system`, `searchable`, `filterable` (boolean)
- `validation_rules` (string, nullable)
- `configuration` (JSON, cast to Collection)

### Relationships

```php
$attribute->group();   // BelongsTo AttributeGroup
$attribute->models();  // HasMany AttributeModel
```

### Field Types

Registered via `FieldTypeManifest`:
- `text`, `number`, `translated_text`, `toggle`, `dropdown`, `list_field`

### Custom Field Types

```php
app(FieldTypeManifest::class)->add(MyFieldType::class);
```

---

## AttributeModel

### Model: `Lunar\Kernel\Models\AttributeModel`

Declares which model types an attribute applies to.

### Fields

- `attribute_id` (FK → Attribute, cascade delete)
- `model_type` (string — morph alias, e.g. `product`, `product_variant`, `brand`)

Unique constraint on `(attribute_id, model_type)`.

### Usage

```php
// Mark an attribute as applying to Product and Brand
$attribute->models()->createMany([
    ['model_type' => 'product'],
    ['model_type' => 'brand'],
]);

// Query attributes for a model type
Attribute::whereHas('models', fn ($q) => $q->where('model_type', 'product'))->get();
```

---

## HasMappedAttributes Trait

For non-typed models (Brand, Collection, Customer) that need their applicable attributes:

```php
use Lunar\Kernel\Models\Concerns\HasMappedAttributes;

class Brand extends BaseModel
{
    use HasMappedAttributes;
}

// Usage
$brand->mappedAttributes();  // Collection of Attribute ordered by position
```

---

## HasAttributeData Trait

Add to any model that stores attribute values:

```php
use Lunar\Kernel\Models\Concerns\HasAttributeData;

class Product extends BaseModel
{
    use HasAttributeData;
    // attribute_data column is cast automatically to AsAttributeData
}
```

### Accessing Values

```php
$product->attr('description');  // Get attribute value
$product->attribute_data;       // Raw AttributeData collection
```

---

## Attributables — ProductType Assignment

### Table: `lunar_attributables`

A polymorphic pivot that connects ProductType (or future entities) to specific Attributes.

### Usage

```php
$productType->mappedAttributes();    // MorphToMany Attribute
$productType->productAttributes();   // Collection filtered by model_type = 'product'
$productType->variantAttributes();   // Collection filtered by model_type = 'product_variant'

// Assign attributes
$productType->mappedAttributes()->syncWithoutDetaching([$attr1->id, $attr2->id]);

// Product / ProductVariant convenience
$product->mappedAttributes();         // delegates to productType->productAttributes()
$variant->mappedAttributes();         // delegates to product->productType->variantAttributes()
```

---

## Architecture Summary

```
AttributeGroup ("Details", handle: "details")
  └── Attribute "name"        (position: 1, required: true)
        ├── AttributeModel model_type: "product"
        ├── AttributeModel model_type: "brand"
        └── AttributeModel model_type: "collection"
  └── Attribute "description" (position: 2, required: false)
        ├── AttributeModel model_type: "product"
        └── AttributeModel model_type: "brand"

ProductType "Stock"
  └── attributables → Attribute "name"         (applies to product)
  └── attributables → Attribute "description"  (applies to product)
  └── attributables → Attribute "sku"          (applies to product_variant)

Brand / Collection / Customer
  └── HasMappedAttributes → filtered by own morph alias
```

**Key rule:** Brand and Collection get their attributes via `HasMappedAttributes` (queries `attribute_models`). Product and ProductVariant get theirs via `ProductType.mappedAttributes()` filtered by model type.