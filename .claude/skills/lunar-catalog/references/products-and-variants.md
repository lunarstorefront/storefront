# Products and Variants Reference

## Product Model — `Lunar\Catalog\Models\Product`

### Traits

`HasAttributeData`, `HasChannels`, `HasCustomerGroups`, `HasFactory`, `HasMedia`, `HasStates`, `HasTags`, `HasUrls`, `LogsActivity`

### Fields

| Field | Type | Notes |
|-------|------|-------|
| product_type_id | foreignId | Required |
| brand_id | foreignId | Nullable |
| name | json | Translatable |
| status | string | Cast to ProductState |
| attribute_data | json | Cast to AsAttributeData |

### Relationships

```php
$product->productType();       // BelongsTo ProductType
$product->brand();             // BelongsTo Brand
$product->variants();          // HasMany ProductVariant
$product->variant();           // HasOne ProductVariant (first/only)
$product->prices();            // HasManyThrough Price (via variants)
$product->collections();       // BelongsToMany Collection
$product->productOptions();    // BelongsToMany ProductOption
$product->associations();      // HasMany ProductAssociation
$product->inverseAssociations(); // HasMany (where target)
```

### States

- `Lunar\Catalog\States\Product\Draft`
- `Lunar\Catalog\States\Product\Active`
- `Lunar\Catalog\States\Product\Discontinued`

Transitions: Draft→Active, Active→Draft, Active→Discontinued, Discontinued→Draft

### Casts

- `name` → `AsTranslatable`
- `status` → `ProductState`

## ProductVariant Model — `Lunar\Catalog\Models\ProductVariant`

### Implements

`Lunar\Kernel\Contracts\Purchasable`, `Spatie\MediaLibrary\HasMedia`

### Traits

`HasAttributeData`, `HasDimensions`, `HasFactory`, `HasMedia`, `HasPrices`, `HasTaxClass`, `LogsActivity`

### Fields

| Field | Type | Notes |
|-------|------|-------|
| product_id | foreignId | Required |
| tax_class_id | foreignId | Required |
| tax_ref | string | Nullable |
| sku | string | Nullable |
| gtin | string | Nullable |
| mpn | string | Nullable |
| ean | string | Nullable |
| unit_quantity | integer | Default: 1 |
| min_quantity | integer | Default: 1 |
| quantity_increment | integer | Default: 1 |
| stock | integer | Default: 0 |
| backorder | integer | Default: 0 |
| purchasable | string | PurchasableStatus enum |
| shippable | boolean | Default: true |
| enabled | boolean | Default: true |
| length, width, height, weight, volume | decimal | Nullable, with _unit columns |
| attribute_data | json | Cast to AsAttributeData |

### Purchasable Interface Methods

```php
getPrices(): Collection            // All Price models
getUnitQuantity(): int             // Units per sale
getTaxClass(): TaxClass            // Tax classification
getTaxReference(): ?string         // External tax ref
getType(): string                  // 'product_variant'
getDescription(): string           // Display name
getIdentifier(): string            // SKU or ID
isShippable(): bool                // Physical product flag
canBeFulfilledAtQuantity(int $qty): bool  // Stock check
getTotalInventory(): int           // stock + backorder
```

### PurchasableStatus Enum

- `Always` — always purchasable
- `InStock` — only when stock > 0
- `Backorder` — purchasable up to stock + backorder

## ProductType Model — `Lunar\Catalog\Models\ProductType`

ProductType controls which attributes are available for its Products and ProductVariants. It uses a polymorphic `attributables` pivot to assign Attributes.

### Fields

- `name` (string)

### Relationships

```php
$type->products();              // HasMany Product
$type->mappedAttributes();      // MorphToMany Attribute (all assigned attributes)
$type->productAttributes();     // Collection filtered to model_type = 'product'
$type->variantAttributes();     // Collection filtered to model_type = 'product_variant'
```

### Assigning Attributes

```php
// Assign individual attributes to a product type
$productType->mappedAttributes()->syncWithoutDetaching([$attr1->id, $attr2->id]);

// Get attributes for product edit form
$productType->productAttributes();   // attributes applicable to Product
$productType->variantAttributes();   // attributes applicable to ProductVariant
```

### Product and Variant Convenience

```php
$product->mappedAttributes();   // delegates to $this->productType->productAttributes()
$variant->mappedAttributes();   // delegates to $this->product->productType->variantAttributes()
```

### Example

```php
$seoTitle  = Attribute::where('handle', 'meta_title')->first();
$sku       = Attribute::where('handle', 'sku')->first();
$stockType = ProductType::where('name', 'Stock')->first();

// Both Product and ProductVariant attributes are attached to the same ProductType
$stockType->mappedAttributes()->syncWithoutDetaching([$seoTitle->id, $sku->id]);

// Retrieve filtered by usage
$stockType->productAttributes();   // returns $seoTitle (model_type = 'product')
$stockType->variantAttributes();   // returns $sku (model_type = 'product_variant')
```

## Brand Model — `Lunar\Catalog\Models\Brand`

### Traits

`HasAttributeData`, `HasChannels`, `HasFactory`, `HasMappedAttributes`, `HasMedia`, `HasTags`, `LogsActivity`

### Fields

- `name` (string)
- `attribute_data` (json)

### Relationships

```php
$brand->products();         // HasMany Product
$brand->collections();      // BelongsToMany Collection
$brand->mappedAttributes(); // Collection of Attribute (via HasMappedAttributes trait, filtered by 'brand' morph alias)
```

## ProductOption Model — `Lunar\Catalog\Models\ProductOption`

### Fields

- `name` (json) — translatable
- `label` (json, nullable)
- `handle` (string) — unique
- `shared` (boolean) — reusable across products
- `position` (integer)

### Scopes

```php
ProductOption::shared()->get();     // Shared options
ProductOption::exclusive()->get();  // Product-specific options
```

### Relationships

```php
$option->values();    // HasMany ProductOptionValue
$option->products();  // BelongsToMany Product
```

## ProductOptionValue Model — `Lunar\Catalog\Models\ProductOptionValue`

### Fields

- `product_option_id` (foreignId)
- `name` (json) — translatable
- `position` (integer)

### Relationships

```php
$value->option();    // BelongsTo ProductOption
$value->variants();  // BelongsToMany ProductVariant
```

## ProductAssociation Model — `Lunar\Catalog\Models\ProductAssociation`

### Fields

- `product_id` (foreignId) — source
- `target_id` (foreignId) — target product
- `type` (string) — ProductAssociationType enum

### ProductAssociationType Enum

- `Related`
- `Upsell`
- `CrossSell`