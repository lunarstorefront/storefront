<?php

namespace Lunar\Storefront;

enum StorefrontPage: string
{
    case Global = 'global';

    case ProductsShow = 'products.show';

    case CollectionsShow = 'collections.show';

    case BrandsIndex = 'brands.index';

    case BrandsShow = 'brands.show';

    case CartIndex = 'carts.index';

    case SearchIndex = 'search.index';

    case AccountIndex = 'account.index';

    case AccountOrdersIndex = 'account.orders.index';

    case AccountAddressesIndex = 'account.addresses.index';

    case AccountProfileIndex = 'account.profile.index';

    case CheckoutIndex = 'sales.checkout.index';
}
