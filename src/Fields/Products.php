<?php

namespace craft\digitalproducts\fields;

use Craft;
use craft\digitalproducts\elements\Product;
use craft\fields\BaseRelationField;

/**
 * Class Digital Product Field
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since     2.0
 */
class Products extends BaseRelationField
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Digital Products');
    }

    /**
     * @inheritdoc
     */
    public static function elementType(): string
    {
        return Product::class;
    }

    /**
     * @inheritdoc
     */
    public static function defaultSelectionLabel(): string
    {
        return Craft::t('commerce', 'Add a digital product');
    }
}
