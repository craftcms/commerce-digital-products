<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Database;

/**
 * The Digital Products database tables (unprefixed; the connection adds the table prefix).
 */
abstract class Table
{
    public const string LICENSES = 'digitalproducts_licenses';

    public const string PRODUCTS = 'digitalproducts_products';

    public const string PRODUCT_TYPES = 'digitalproducts_producttypes';

    public const string PRODUCT_TYPES_SITES = 'digitalproducts_producttypes_sites';
}
