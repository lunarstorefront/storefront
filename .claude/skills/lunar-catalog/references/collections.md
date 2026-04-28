# Collections Reference

## CollectionGroup Model — `Lunar\Catalog\Models\CollectionGroup`

### Fields

- `name` (string)
- `handle` (string) — auto-slugified from name, unique

### Relationships

```php
$group->collections();  // HasMany Collection
```

## Collection Model — `Lunar\Catalog\Models\Collection`

### Traits

`HasAttributeData`, `HasChannels`, `HasCustomerGroups`, `HasFactory`, `HasMedia`, `HasStates`, `HasTags`, `HasUrls`, `LogsActivity`, `NodeTrait`

### Fields

| Field | Type | Notes |
|-------|------|-------|
| collection_group_id | foreignId | Required |
| _lft | integer | Nested set left |
| _rgt | integer | Nested set right |
| parent_id | foreignId | Nullable, self-referencing |
| name | json | Translatable |
| type | string | Default: 'static' |
| attribute_data | json | Cast to AsAttributeData |
| sort | string | Default: 'custom' |
| status | string | Cast to CollectionState |

### States

- `Lunar\Catalog\States\Collection\Draft`
- `Lunar\Catalog\States\Collection\Active`
- `Lunar\Catalog\States\Collection\Archived`

### Nested Set Operations (kalnoy/nestedset)

```php
$collection->parent;       // Parent collection
$collection->children;     // Direct children
$collection->ancestors;    // All ancestors to root
$collection->descendants;  // All descendants
$collection->siblings;     // Same-level nodes

// Tree building
$tree = Collection::get()->toTree();

// Moving nodes
$collection->appendToNode($parent)->save();
$collection->prependToNode($parent)->save();
$collection->insertAfterNode($sibling)->save();
```

### Relationships

```php
$collection->group();      // BelongsTo CollectionGroup
$collection->products();   // BelongsToMany Product (with position pivot)
$collection->brands();     // BelongsToMany Brand
```

### Product Sorting

Products in collections have a `position` pivot column for custom ordering.
```php
$collection->products()->attach($product, ['position' => 1]);
```