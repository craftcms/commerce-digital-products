<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Gql\Types\Generators;

use CraftCms\Cms\Gql\Contracts\GeneratorInterface;
use CraftCms\Cms\Gql\Contracts\SingleGeneratorInterface;
use CraftCms\Cms\Gql\GqlEntityRegistry;
use CraftCms\Cms\Gql\Types\Generators\Generator;
use CraftCms\Cms\Gql\Types\ObjectType;
use CraftCms\Cms\Support\Facades\Gql;
use CraftCms\DigitalProducts\Elements\Product as ProductElement;
use CraftCms\DigitalProducts\Gql\Interfaces\Elements\Product as ProductInterface;
use CraftCms\DigitalProducts\Gql\Types\Elements\Product as ProductTypeElement;
use CraftCms\DigitalProducts\ProductTypes;
use CraftCms\DigitalProducts\Support\Gql as GqlHelper;

/**
 * Generates a GraphQL object type per digital product type (`{handle}_Product`).
 */
class ProductType extends Generator implements GeneratorInterface, SingleGeneratorInterface
{
    public static function generateTypes(mixed $context = null): array
    {
        $gqlTypes = [];

        foreach (app(ProductTypes::class)->getAllProductTypes() as $productType) {
            if (! GqlHelper::isSchemaAwareOf(ProductElement::gqlScopesByContext($productType))) {
                continue;
            }

            $type = static::generateType($productType);
            $gqlTypes[$type->name] = $type;
        }

        return $gqlTypes;
    }

    public static function generateType(mixed $context): ObjectType
    {
        $typeName = ProductElement::gqlTypeNameByContext($context);

        return GqlEntityRegistry::getOrCreate($typeName, fn () => new ProductTypeElement([
            'name' => $typeName,
            'fields' => function () use ($context, $typeName) {
                $contentFields = self::getContentFields($context->getFieldLayout());

                return Gql::prepareFieldDefinitions(
                    array_merge(ProductInterface::getFieldDefinitions(), $contentFields),
                    $typeName,
                );
            },
        ]));
    }
}
