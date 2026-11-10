<?php

namespace craft\digitalproducts\records;

use craft\commerce\records\Order;
use craft\db\ActiveRecord;
use craft\records\Element;
use craft\records\User;
use yii\db\ActiveQueryInterface;

/**
 * License record.
 *
 * @property int $id         License id
 * @property int $productId  Product id
 * @property int $orderId    Order id
 * @property string $licenseKey License key
 * @property string $ownerName  Name of the license owner
 * @property string $ownerEmail Email of the license owner
 * @property int $userId     User id
 * @property Element $element    Element
 * @property Product $product    Product
 * @property Order $order      Order
 * @property User $user       User
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2016, Pixel & Tonic, Inc.
 */
class License extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%digitalproducts_licenses}}';
    }

    /**
     * Return the license's element.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }

    /**
     * Return the licensed product.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getProduct(): ActiveQueryInterface
    {
        return $this->hasOne(Product::class, ['id' => 'productId']);
    }

    /**
     * Return the order that contained this license.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getOrder(): ActiveQueryInterface
    {
        return $this->hasOne(Order::class, ['id' => 'orderId']);
    }

    /**
     * Return the user owning this license.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getUser(): ActiveQueryInterface
    {
        return $this->hasOne(User::class, ['id' => 'userId']);
    }
}
