<?php

namespace craft\commerce\digitalProducts\fields;

use Craft;
use craft\commerce\digitalProducts\elements\Product;
use craft\fields\BaseRelationField;

/**
 * Class Digital Product Field
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since     2.0
 *
 */
class Products extends BaseRelationField
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return Craft::t('commerce', 'Digital Products');
    }

    /**
     * @inheritdoc
     */
    protected static function elementType(): string
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
