<?php

namespace craft\digitalproducts\events;

use craft\digitalproducts\models\ProductType;
use yii\base\Event;

/**
 * Product type event class.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class ProductTypeEvent extends Event
{
    /**
     * @var ProductType|null The product type model associated with the event.
     */
    public $productType;

    /**
     * @var bool Whether the category group is brand new
     */
    public $isNew = false;
}
