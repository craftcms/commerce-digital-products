<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\digitalproducts\db;

/**
 * This class provides public constants for defining Digital Products’ database table names. Do not use these in migrations.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
abstract class Table
{
    public const LICENSES = '{{%digitalproducts_licenses}}';
    public const PRODUCTS = '{{%digitalproducts_products}}';
    public const PRODUCT_TYPES = '{{%digitalproducts_producttypes}}';
    public const PRODUCT_TYPES_SITES = '{{%digitalproducts_producttypes_sites}}';
}
