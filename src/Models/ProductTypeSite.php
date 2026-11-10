<?php

namespace craft\digitalproducts\records;

use craft\db\ActiveRecord;
use craft\records\Site;
use yii\db\ActiveQueryInterface;

/**
 * Product type site record.
 *
 * @property int $id            Setting id
 * @property int $productTypeId Product type id
 * @property int|null $siteId        Site id
 * @property string $uriFormat     Uri format
 * @property bool $hasUrls       Do products with this site/type combo have urls?
 * @property string $template      Template to use
 * @property Site $site          Site
 * @property ProductType $productType   Product Type
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2016, Pixel & Tonic, Inc.
 */
class ProductTypeSite extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%digitalproducts_producttypes_sites}}';
    }

    /**
     * Return the product type for these settings.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getProductType(): ActiveQueryInterface
    {
        return $this->hasOne(ProductType::class, ['id', 'productTypeId']);
    }

    /**
     * Return the Site for these settings.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getSite(): ActiveQueryInterface
    {
        return $this->hasOne(Site::class, ['id', 'siteId']);
    }
}
