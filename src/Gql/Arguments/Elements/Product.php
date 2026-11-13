<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Gql\Arguments\Elements;

use CraftCms\Cms\Gql\Arguments\ElementArguments;
use CraftCms\Cms\Gql\Types\DateTime;
use CraftCms\Cms\Gql\Types\QueryArgument;
use CraftCms\Cms\Support\Facades\Gql;
use CraftCms\DigitalProducts\Elements\Product as ProductElement;
use CraftCms\DigitalProducts\ProductTypes;
use GraphQL\Type\Definition\Type;
use Override;

class Product extends ElementArguments
{
    #[Override]
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), self::getContentArguments(), [
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

    #[Override]
    public static function getContentArguments(): array
    {
        return array_merge(parent::getContentArguments(), Gql::getContentArguments(
            contexts: app(ProductTypes::class)->getAllProductTypes(),
            elementType: ProductElement::class,
        ));
    }
}
