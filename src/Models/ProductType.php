<?php

namespace craft\digitalproducts\records;

use craft\db\ActiveRecord;
use craft\records\FieldLayout;
use yii\db\ActiveQueryInterface;

/**
 * Product Type record.
 *
 * @property int $id            Product type id
 * @property int|null $fieldLayoutId Field layout id
 * @property string $name          Product type name
 * @property string $handle        Product type handle
 * @property string $skuFormat     SKU format
 * @property FieldLayout $fieldLayout   Field layout
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2016, Pixel & Tonic, Inc.
 */
class ProductType extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%digitalproducts_producttypes}}';
    }

    /**
     * Return the product type's field layout.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getFieldLayout(): ActiveQueryInterface
    {
        return $this->hasOne(FieldLayout::class, ['id' => 'fieldLayoutId']);
    }
}
