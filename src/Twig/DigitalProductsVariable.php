<?php

namespace craft\digitalproducts\variables;

use craft\digitalproducts\elements\db\LicenseQuery;
use craft\digitalproducts\elements\db\ProductQuery;
use craft\digitalproducts\elements\License;
use craft\digitalproducts\elements\Product;
use craft\digitalproducts\models\ProductType;
use craft\digitalproducts\Plugin as DigitalProductsPlugin;

/**
 * Variable class.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2016, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 */
class DigitalProducts
{
    /**
     * Return the product types service.
     *
     * @return ProductType[]
     */
    public function getProductTypes(): array
    {
        return DigitalProductsPlugin::getInstance()->getProductTypes()->getAllProductTypes();
    }

    /**
     * Get licenses service.
     *
     * @return LicenseQuery
     */
    public function licenses(): LicenseQuery
    {
        return License::find();
    }

    /**
     * Get Digital Products.
     *
     * @return ProductQuery
     */
    public function products(): ProductQuery
    {
        return Product::find();
    }

    /**
     * Get the plugin instance.
     *
     * @return DigitalProductsPlugin
     */
    public function getPlugin(): DigitalProductsPlugin
    {
        return DigitalProductsPlugin::getInstance();
    }
}
