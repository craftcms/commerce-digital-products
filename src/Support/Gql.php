<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Support;

use CraftCms\Cms\Gql\Data\GqlSchema;
use CraftCms\Cms\Gql\GqlHelper;
use CraftCms\DigitalProducts\Data\ProductType;
use CraftCms\DigitalProducts\ProductTypes;

class Gql extends GqlHelper
{
    /**
     * Whether the current schema can query digital products.
     */
    public static function canQueryProducts(): bool
    {
        return isset(self::extractAllowedEntitiesFromSchema()['digitalProductTypes']);
    }

    /**
     * @return ProductType[] The product types the schema can read.
     */
    public static function getSchemaContainedProductTypes(?GqlSchema $schema = null): array
    {
        return array_values(array_filter(
            app(ProductTypes::class)->getAllProductTypes(),
            fn (ProductType $productType) => static::isSchemaAwareOf("digitalProductTypes.$productType->uid", $schema),
        ));
    }
}
