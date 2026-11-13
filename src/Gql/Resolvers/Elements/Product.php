<?php

declare(strict_types=1);

namespace CraftCms\DigitalProducts\Gql\Resolvers\Elements;

use BadMethodCallException;
use CraftCms\Cms\Element\ElementCollection;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Gql\Resolvers\ElementResolver;
use CraftCms\DigitalProducts\Data\ProductType;
use CraftCms\DigitalProducts\Database\Table;
use CraftCms\DigitalProducts\Elements\Product as ProductElement;
use CraftCms\DigitalProducts\Support\Gql as GqlHelper;

class Product extends ElementResolver
{
    public static function prepareQuery(mixed $source, array $arguments, ?string $fieldName = null): mixed
    {
        // Start fresh at the beginning of a resolver chain, otherwise use the prepared element query
        $query = $source === null ? ProductElement::find() : $source->$fieldName;

        // If it's preloaded, it's preloaded
        if (! $query instanceof ElementQueryInterface) {
            return $query;
        }

        foreach ($arguments as $key => $value) {
            try {
                $query->$key($value);
            } catch (BadMethodCallException $e) {
                if ($value !== null) {
                    throw $e;
                }
            }
        }

        if (! GqlHelper::canQueryProducts()) {
            return ElementCollection::empty();
        }

        $query->whereIn(Table::PRODUCTS.'.typeId', array_map(
            fn (ProductType $productType) => $productType->id,
            GqlHelper::getSchemaContainedProductTypes(),
        ));

        return $query;
    }
}
