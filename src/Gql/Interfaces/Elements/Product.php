<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\digitalproducts\gql\interfaces\elements;

use Craft;
use craft\digitalproducts\elements\Product as ProductElement;
use craft\digitalproducts\gql\types\generators\ProductType;
use craft\gql\GqlEntityRegistry;
use craft\gql\interfaces\Element;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;

/**
 * Class Product
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.4
 */
class Product extends Element
{
    /**
     * @inheritdoc
     */
    public static function getTypeGenerator(): string
    {
        return ProductType::class;
    }

    /**
     * @inheritdoc
     */
    public static function getType($fields = null): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        $type = GqlEntityRegistry::createEntity(self::getName(), new InterfaceType([
            'name' => static::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the interface implemented by all digital products.',
            'resolveType' => function(ProductElement $value) {
                return $value->getGqlTypeName();
            },
        ]));

        ProductType::generateTypes();

        return $type;
    }

    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return 'DigitalProductInterface';
    }

    /**
     * @inheritdoc
     */
    public static function getFieldDefinitions(): array
    {
        return Craft::$app->getGql()->prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), [
            'price' => [
                'name' => 'price',
                'type' => Type::float(),
                'description' => 'The price of the digital product.',
            ],
            'sku' => [
                'name' => 'sku',
                'type' => Type::string(),
                'description' => 'The sku of the digital product.',
            ],
            'productTypeId' => [
                'name' => 'productTypeId',
                'type' => Type::int(),
                'description' => 'The ID of the product type that contains the digital product.',
            ],
            'productTypeHandle' => [
                'name' => 'productTypeHandle',
                'type' => Type::string(),
                'description' => 'The handle of the product type that contains the digital product.',
            ],
        ]), self::getName());
    }
}
