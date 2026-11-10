<?php

namespace craft\digitalproducts\records;

use craft\commerce\records\TaxCategory;
use craft\db\ActiveRecord;
use craft\records\Element;
use DateTime;
use yii\db\ActiveQueryInterface;

/**
 * Product record.
 *
 * @property int $id            Product id
 * @property int $typeId        Product type id
 * @property int $taxCategoryId Product tax category id
 * @property DateTime $postDate      Product post date
 * @property DateTime $expiryDate    Product expiry date
 * @property bool $promotable    Can sales/discounts be applied?
 * @property Element $element       Element
 * @property ProductType $type          Product type
 * @property TaxCategory $taxCategory   Tax category
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2016, Pixel & Tonic, Inc.
 */
class Product extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%digitalproducts_products}}';
    }

    /**
     * Return the product's type.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getType(): ActiveQueryInterface
    {
        return $this->hasOne(ProductType::class, ['id' => 'typeId']);
    }

    /**
     * Return the product's tax category.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getTaxCategory(): ActiveQueryInterface
    {
        return $this->hasOne(TaxCategory::class, ['id' => 'taxCategoryId']);
    }

    /**
     * Returns the entry’s element.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }
}
