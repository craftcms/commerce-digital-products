<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Gql\Interfaces\Elements;

use CraftCms\Cms\Gql\GqlEntityRegistry;
use CraftCms\Cms\Gql\Interfaces\Element;
use CraftCms\Cms\Support\Facades\Gql;
use CraftCms\DigitalProducts\Gql\Types\Generators\ProductType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;
use Override;

class Product extends Element
{
    #[Override]
    public static function getTypeGenerator(): string
    {
        return ProductType::class;
    }

    #[Override]
    public static function getType(): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        $type = GqlEntityRegistry::createEntity(self::getName(), new InterfaceType([
            'name' => static::getName(),
            'fields' => self::class.'::getFieldDefinitions',
            'description' => 'This is the interface implemented by all digital products.',
            'resolveType' => self::class.'::resolveElementTypeName',
        ]));

        ProductType::generateTypes();

        return $type;
    }

    #[Override]
    public static function getName(): string
    {
        return 'DigitalProductInterface';
    }

    #[Override]
    public static function getFieldDefinitions(): array
    {
        return Gql::prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), [
            'price' => [
                'name' => 'price',
                'type' => Type::float(),
                'description' => 'The price of the digital product.',
            ],
            'sku' => [
                'name' => 'sku',
                'type' => Type::string(),
                'description' => 'The SKU of the digital product.',
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
