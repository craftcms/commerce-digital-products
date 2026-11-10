<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\digitalproducts\gql\arguments\elements;

use Craft;
use craft\behaviors\FieldLayoutBehavior;
use craft\digitalproducts\elements\Product as ProductElement;
use craft\digitalproducts\models\ProductType;
use craft\digitalproducts\Plugin;
use craft\gql\base\ElementArguments;
use craft\gql\types\DateTime;
use craft\gql\types\QueryArgument;
use GraphQL\Type\Definition\Type;

/**
 * Class Product
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.4
 */
class Product extends ElementArguments
{
    /**
     * @inheritdoc
     */
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(),  self::getContentArguments(), [
            'sku' => [
                'name' => 'sku',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the SKU of the digital product.',
            ],
            'before' => [
                'name' => 'before',
                'type' => DateTime::getType(),
                'description' => 'Narrows the query results to only digital products whose Post Date is before the given value.',
            ],
            'after' => [
                'name' => 'after',
                'type' => DateTime::getType(),
                'description' => 'Narrows the query results to only digital products whose Post Date is after the given value.',
            ],
            'type' => [
                'name' => 'type',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on the product type the products belong to per the product type’s handles.',
            ],
            'typeId' => [
                'name' => 'typeId',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the product types the products belong to, per the product type IDs.',
            ],
        ]);
    }

    /**
     * @inheritdoc
     */
    public static function getContentArguments(): array
    {
        /** @var ProductType[]|FieldLayoutBehavior[] $productTypes */
        $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();
        $productTypeFieldArguments = Craft::$app->getGql()->getContentArguments($productTypes, ProductElement::class);

        return array_merge(parent::getContentArguments(), $productTypeFieldArguments);
    }
}
