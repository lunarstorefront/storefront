# Cart Actions Reference

## AddOrUpdatePurchasable

Adds a purchasable item to the cart or updates its quantity if already present.
```php
$cart->add($variant, quantity: 2, meta: ['gift_wrap' => true]);
```

## RemovePurchasable

Removes a line from the cart.
```php
$cart->remove($cartLineId);
```

## UpdateCartLine

Updates quantity or meta on an existing cart line.
```php
$cart->updateLine($cartLineId, quantity: 5, meta: ['note' => 'Updated']);
```

## AddAddress

Sets a billing or shipping address on the cart.
```php
$cart->setShippingAddress([
    'country_id' => $country->id,
    'first_name' => 'Jane',
    'last_name' => 'Doe',
    'line_one' => '456 Oak Ave',
    'city' => 'Portland',
    'state' => 'OR',
    'postcode' => '97201',
    'contact_email' => 'jane@example.com',
    'contact_phone' => '555-0100',
]);

$cart->setBillingAddress([...]);
```

### CartAddress Fields

| Field | Type | Notes |
|-------|------|-------|
| cart_id | foreignId | Auto-set |
| country_id | foreignId | Required |
| type | string | shipping or billing |
| title | string | Nullable |
| first_name | string | Nullable |
| last_name | string | Nullable |
| company_name | string | Nullable |
| line_one | string | Nullable |
| line_two | string | Nullable |
| line_three | string | Nullable |
| city | string | Nullable |
| state | string | Nullable |
| postcode | string | Nullable |
| contact_email | string | Nullable |
| contact_phone | string | Nullable |
| delivery_instructions | string | Nullable |
| shipping_option | string | Nullable |
| meta | json | Nullable |

## SetShippingOption

Sets the selected shipping option identifier on the cart.
```php
$cart->setShippingOption('flat-rate');
```

## AssociateUser

Associates a cart with an authenticated user with a merge policy.
```php
$cart->associate($user, 'merge');    // Merge with user's existing cart
$cart->associate($user, 'override'); // Replace user's existing cart
```

## MergeCart

Merges one cart into another (e.g., guest cart into user cart on login).
```php
// Handled automatically by associate() with 'merge' policy
```

## GenerateFingerprint

Generates a hash fingerprint for cart content validation.
```php
$cart->generateFingerprint();
// Optionally validated in config/sales.php: 'fingerprint_validation' => true
```

## Cart Session Facade — `Lunar\Sales\Facades\CartSession`

```php
CartSession::current();                          // Get/create session cart
CartSession::use($cart);                         // Set as current
CartSession::forget();                           // Clear session
CartSession::associate($cart, $user, 'merge');   // Link to user
CartSession::setChannel($channel);
CartSession::setCurrency($currency);
CartSession::setRegion($region);
```