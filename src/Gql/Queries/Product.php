<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Gql\Queries;

use CraftCms\Cms\Gql\Queries\Query;
use CraftCms\DigitalProducts\Gql\Arguments\Elements\Product as ProductArguments;
use CraftCms\DigitalProducts\Gql\Interfaces\Elements\Product as ProductInterface;
use CraftCms\DigitalProducts\Gql\Resolvers\Elements\Product as ProductResolver;
use CraftCms\DigitalProducts\Support\Gql as GqlHelper;
use GraphQL\Type\Definition\Type;

class Product extends Query
{
    public static function getQueries(bool $checkToken = true): array
    {
        if ($checkToken && ! GqlHelper::canQueryProducts()) {
            return [];
        }

        return [
            'digitalProducts' => [
                'type' => Type::listOf(ProductInterface::getType()),
                'args' => ProductArguments::getArguments(),
                'resolve' => ProductResolver::class.'::resolve',
                'description' => 'Used to query for digital products.',
            ],
            'digitalProduct' => [
                'type' => ProductInterface::getType(),
                'args' => ProductArguments::getArguments(),
                'resolve' => ProductResolver::class.'::resolveOne',
                'description' => 'Used to query for a digital product.',
            ],
        ];
    }
}
